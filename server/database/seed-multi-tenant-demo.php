#!/usr/bin/env php
<?php
declare(strict_types=1);

use app\platform\service\PdoTenantOwnerAdminProvisioner;
use PeanutAdmin\Kernel\Identity\PasswordHasher;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoAuditRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoIdentityRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoMembershipRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoPlatformRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTenantRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTransactionManager;
use PeanutAdmin\Kernel\Platform\Bootstrap\BootstrapService;

require __DIR__ . '/install.php';

function demoMultiFail(string $message): never
{
    fwrite(STDERR, "Multi-Tenant demo seed failed: {$message}\n");
    exit(1);
}

function demoMultiRequired(string $name): string
{
    $value = trim((string)(getenv($name) ?: ''));
    if ($value === '') {
        throw new RuntimeException("{$name} is required");
    }
    return $value;
}

function demoMultiBinding(PDO $pdo, int $tenantId, string $host): void
{
    $host = strtolower(rtrim(trim($host), '.'));
    if (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
        throw new RuntimeException("invalid demo Tenant host: {$host}");
    }
    $query = $pdo->prepare(
        "SELECT id, tenant_id, status FROM pa_tenant_entry_binding WHERE host = ? AND client_key = 'admin-web' LIMIT 1"
    );
    $query->execute([$host]);
    $row = $query->fetch(PDO::FETCH_ASSOC);
    if (is_array($row) && (int)$row['tenant_id'] !== $tenantId) {
        throw new RuntimeException("demo Tenant host is already owned by another Tenant: {$host}");
    }
    if (is_array($row)) {
        $update = $pdo->prepare("UPDATE pa_tenant_entry_binding SET status = 'active' WHERE id = ?");
        $update->execute([(int)$row['id']]);
        return;
    }
    $insert = $pdo->prepare(
        "INSERT INTO pa_tenant_entry_binding (tenant_id,host,client_key,status) VALUES (?,?,'admin-web','active')"
    );
    $insert->execute([$tenantId, $host]);
}

/** @return list<string> */
function demoMultiHostList(string $name): array
{
    $hosts = [];
    foreach (explode(',', (string)(getenv($name) ?: '')) as $host) {
        $host = strtolower(rtrim(trim($host), '.'));
        if ($host !== '') {
            $hosts[] = $host;
        }
    }
    return array_values(array_unique($hosts));
}

function demoMultiOwner(PDO $pdo, int $tenantId): array
{
    $statement = $pdo->prepare(<<<'SQL'
SELECT tm.account_id, tm.id AS member_id, r.id AS role_id, c.identifier_normalized AS email
FROM pa_tenant_member tm
JOIN pa_member_role mr ON mr.tenant_id = tm.tenant_id AND mr.tenant_member_id = tm.id
JOIN pa_role r ON r.tenant_id = mr.tenant_id AND r.id = mr.role_id
  AND r.`key` = 'core.tenant-owner' AND r.status = 'active'
JOIN pa_credential c ON c.account_id = tm.account_id
  AND c.kind = 'email_password' AND c.status = 'active'
WHERE tm.tenant_id = :tenant_id AND tm.status = 'active'
LIMIT 1
SQL);
    $statement->execute(['tenant_id' => $tenantId]);
    $owner = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($owner)) {
        throw new RuntimeException("Tenant {$tenantId} has no active owner");
    }
    return $owner;
}

function demoMultiTenant(
    PDO $pdo,
    BootstrapService $bootstrap,
    PdoTenantOwnerAdminProvisioner $adminProvisioner,
    int $platformOperatorId,
    string $code,
    string $name,
    string $email,
    string $password
): int {
    $statement = $pdo->prepare('SELECT id, status FROM pa_tenant WHERE code = ? ORDER BY id LIMIT 1');
    $statement->execute([$code]);
    $tenant = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($tenant)) {
        $candidate = $bootstrap->provisionTenantOwnerCandidate(
            $platformOperatorId,
            $code,
            $name,
            $email,
            $password,
            "{$name} Owner",
            "demo-{$code}-provision"
        );
        $bootstrap->activateTenantOwner(
            $platformOperatorId,
            $candidate->tenantId,
            $candidate->memberId,
            "demo-{$code}-owner-activate"
        );
        $adminProvisioner->provision(
            $candidate->tenantId,
            $candidate->accountId,
            $candidate->memberId,
            $candidate->roleId,
            $code,
            "{$name} Owner"
        );
        $bootstrap->activateTenant(
            $platformOperatorId,
            $candidate->tenantId,
            "demo-{$code}-activate"
        );
        return $candidate->tenantId;
    }

    $tenantId = (int)$tenant['id'];
    if ($tenant['status'] !== 'active') {
        throw new RuntimeException("existing demo Tenant {$code} is not active");
    }
    $owner = demoMultiOwner($pdo, $tenantId);
    if (!hash_equals($email, strtolower((string)$owner['email']))) {
        throw new RuntimeException("existing demo Tenant {$code} owner email does not match the demo plan");
    }
    $adminProvisioner->provision(
        $tenantId,
        (int)$owner['account_id'],
        (int)$owner['member_id'],
        (int)$owner['role_id'],
        $code,
        "{$name} Owner"
    );
    return $tenantId;
}

function demoMultiMain(): int
{
    if (($_SERVER['argv'][1] ?? '') !== '--apply' || count($_SERVER['argv']) !== 2) {
        fwrite(STDERR, "Usage: server/database/seed-multi-tenant-demo.php --apply\n");
        return 64;
    }
    if (getenv('PEANUT_DEMO_MODE') !== 'enabled') {
        throw new RuntimeException('PEANUT_DEMO_MODE=enabled is required');
    }
    $target = demoMultiRequired('PEANUT_DEPLOYMENT_TARGET');
    $resource = demoMultiRequired('PEANUT_DATABASE_RESOURCE_ID');
    $allowed = [
        'production-candidate' => 'peanut-admin-production-candidate-mysql84',
        'local-multi-tenant-demo' => 'peanut-admin-mysql84-local-multi-tenant-demo',
    ];
    if (($allowed[$target] ?? null) !== $resource) {
        throw new RuntimeException('demo seed target and registered database resource do not match');
    }
    if (demoMultiRequired('DEPLOYMENT_MODE') !== 'multi-tenant') {
        throw new RuntimeException('multi-tenant deployment mode is required');
    }

    $tenantAEmail = strtolower(demoMultiRequired('PEANUT_DEMO_TENANT_A_EMAIL'));
    $tenantBEmail = strtolower(demoMultiRequired('PEANUT_DEMO_TENANT_B_EMAIL'));
    $sharedPassword = demoMultiRequired('PEANUT_DEMO_SHARED_PASSWORD');
    if (filter_var($tenantAEmail, FILTER_VALIDATE_EMAIL) === false
        || filter_var($tenantBEmail, FILTER_VALIDATE_EMAIL) === false
        || $tenantAEmail === $tenantBEmail) {
        throw new RuntimeException('demo Tenant emails must be different valid addresses');
    }
    validateInitialAdminPassword($sharedPassword);
    $tenantAHost = strtolower(rtrim(demoMultiRequired('PEANUT_DEMO_TENANT_A_HOST'), '.'));
    $tenantBHost = strtolower(rtrim(demoMultiRequired('PEANUT_DEMO_TENANT_B_HOST'), '.'));
    $reservedHosts = array_merge(
        demoMultiHostList('PLATFORM_HOSTS'),
        demoMultiHostList('TENANT_ADMIN_HOSTS')
    );
    if (hash_equals($tenantAHost, $tenantBHost)
        || in_array($tenantAHost, $reservedHosts, true)
        || in_array($tenantBHost, $reservedHosts, true)) {
        throw new RuntimeException('demo Tenant hosts must be distinct from Platform and shared Admin hosts');
    }

    $serverDir = dirname(__DIR__);
    loadCoreRuntime($serverDir);
    $config = loadConfig($serverDir);
    $pdo = new PDO(
        sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $config['DB_HOST'],
            $config['DB_PORT'],
            $config['DB_NAME']
        ),
        $config['DB_USER'],
        $config['DB_PASS'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $defaultTenant = $pdo->query(
        "SELECT id, status FROM pa_tenant WHERE code = 'default' ORDER BY id LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);
    if (!is_array($defaultTenant) || $defaultTenant['status'] !== 'active') {
        throw new RuntimeException('fresh default Tenant is unavailable');
    }

    $platform = $pdo->query(
        "SELECT id, account_id FROM pa_platform_operator WHERE status = 'active' ORDER BY id LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);
    if (!is_array($platform)) {
        throw new RuntimeException('active PlatformOperator is unavailable');
    }
    $bootstrap = new BootstrapService(
        new PdoTransactionManager($pdo),
        new PdoIdentityRepository($pdo),
        new PdoTenantRepository($pdo),
        new PdoMembershipRepository($pdo),
        new PdoPlatformRepository($pdo),
        new PdoAuditRepository($pdo),
        new PasswordHasher()
    );
    $adminProvisioner = new PdoTenantOwnerAdminProvisioner($pdo);
    $tenantAId = demoMultiTenant(
        $pdo,
        $bootstrap,
        $adminProvisioner,
        (int)$platform['id'],
        'tenant-a',
        'Tenant A',
        $tenantAEmail,
        $sharedPassword
    );
    $tenantBId = demoMultiTenant(
        $pdo,
        $bootstrap,
        $adminProvisioner,
        (int)$platform['id'],
        'tenant-b',
        'Tenant B',
        $tenantBEmail,
        $sharedPassword
    );

    demoMultiBinding($pdo, $tenantAId, $tenantAHost);
    demoMultiBinding($pdo, $tenantBId, $tenantBHost);

    echo json_encode([
        'status' => 'applied',
        'tenant_a_id' => $tenantAId,
        'tenant_a_email' => $tenantAEmail,
        'tenant_b_id' => $tenantBId,
        'tenant_b_email' => $tenantBEmail,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
    return 0;
}

try {
    exit(demoMultiMain());
} catch (Throwable $exception) {
    demoMultiFail($exception->getMessage());
}
