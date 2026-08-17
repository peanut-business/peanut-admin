#!/usr/bin/env php
<?php
declare(strict_types=1);

use app\common\service\tenant\TenantEntryBindingResolver;
use app\platform\service\PdoTenantOwnerAdminProvisioner;
use PeanutAdmin\Kernel\Identity\PasswordHasher;
use PeanutAdmin\Kernel\Membership\TenantMemberStatus;
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
    $host = TenantEntryBindingResolver::normalizeHost($host);
    $query = $pdo->prepare(
        "SELECT id, tenant_id, status FROM pa_tenant_entry_binding WHERE host = ? AND client_key = 'admin-web' LIMIT 1"
    );
    $query->execute([$host]);
    $row = $query->fetch(PDO::FETCH_ASSOC);
    if (is_array($row) && (int)$row['tenant_id'] !== $tenantId) {
        throw new RuntimeException("demo Tenant host is already owned by another Tenant: {$host}");
    }
    if (is_array($row)) {
        $update = $pdo->prepare(
            "UPDATE pa_tenant_entry_binding SET status = 'active', updated_at = UTC_TIMESTAMP(3) WHERE id = ?"
        );
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
        $host = trim($host);
        if ($host !== '') {
            $hosts[] = TenantEntryBindingResolver::normalizeHost($host);
        }
    }
    return array_values(array_unique($hosts));
}

function demoMultiOwner(PDO $pdo, int $tenantId, string $email): array
{
    $statement = $pdo->prepare(<<<'SQL'
SELECT tm.account_id, tm.id AS member_id, tm.display_name, r.id AS role_id,
       c.identifier_normalized AS email, c.secret_hash
FROM pa_tenant_member tm
JOIN pa_account a ON a.id = tm.account_id AND a.status = 'active'
JOIN pa_member_role mr ON mr.tenant_id = tm.tenant_id AND mr.tenant_member_id = tm.id
JOIN pa_role r ON r.tenant_id = mr.tenant_id AND r.id = mr.role_id
  AND r.`key` = 'core.tenant-owner' AND r.is_builtin = 1 AND r.status = 'active'
JOIN pa_credential c ON c.account_id = tm.account_id
  AND c.kind = 'email_password' AND c.identifier_type = 'email' AND c.status = 'active'
WHERE tm.tenant_id = :tenant_id
  AND tm.status = 'active'
  AND c.identifier_normalized = :email
LIMIT 2
SQL);
    $statement->execute(['tenant_id' => $tenantId, 'email' => $email]);
    $owners = $statement->fetchAll(PDO::FETCH_ASSOC);
    if (count($owners) !== 1) {
        throw new RuntimeException("Tenant {$tenantId} does not have exactly one active owner for {$email}");
    }
    return $owners[0];
}

/** @return array{tenant_id:int,account_id:int,member_id:int,role_id:int,email:string} */
function demoMultiTenant(
    PDO $pdo,
    BootstrapService $bootstrap,
    PdoTenantOwnerAdminProvisioner $adminProvisioner,
    int $platformOperatorId,
    string $code,
    string $name,
    string $email,
    string $password,
    PasswordHasher $passwords
): array {
    $statement = $pdo->prepare(
        'SELECT id, name, display_name, status FROM pa_tenant WHERE code = ? ORDER BY id LIMIT 1'
    );
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
        $bootstrap->activateTenant(
            $platformOperatorId,
            $candidate->tenantId,
            "demo-{$code}-activate"
        );
        $tenantId = $candidate->tenantId;
    } else {
        $tenantId = (int)$tenant['id'];
        if ($tenant['status'] !== 'active'
            || !hash_equals($name, (string)$tenant['name'])
            || !hash_equals($name, (string)$tenant['display_name'])) {
            throw new RuntimeException("existing demo Tenant {$code} does not match the demo plan");
        }
    }

    $owner = demoMultiOwner($pdo, $tenantId, $email);
    if (!$passwords->verify($password, (string)$owner['secret_hash'])) {
        throw new RuntimeException("demo Tenant {$code} credential does not match the published password");
    }
    $adminProvisioner->provision(
        $tenantId,
        (int)$owner['account_id'],
        (int)$owner['member_id'],
        (int)$owner['role_id'],
        $code,
        "{$name} Owner"
    );
    return [
        'tenant_id' => $tenantId,
        'account_id' => (int)$owner['account_id'],
        'member_id' => (int)$owner['member_id'],
        'role_id' => (int)$owner['role_id'],
        'email' => (string)$owner['email'],
    ];
}

function demoMultiEnsureSharedOwner(
    PdoMembershipRepository $memberships,
    int $tenantId,
    int $accountId,
    int $ownerRoleId
): int {
    $member = $memberships->byTenantAndAccount($tenantId, $accountId, true);
    if ($member === null) {
        $member = $memberships->createPending($tenantId, $accountId, 'Tenant A Owner');
    }
    if ($member->status === TenantMemberStatus::Pending) {
        $member = $memberships->transition($tenantId, $member->id, TenantMemberStatus::Active);
    } elseif ($member->status !== TenantMemberStatus::Active) {
        throw new RuntimeException('shared demo Account has an inactive Tenant B membership');
    }
    if (!$memberships->memberHasRole($tenantId, $member->id, 'core.tenant-owner')) {
        $memberships->assignRole($tenantId, $member->id, $ownerRoleId);
    }
    return $member->id;
}

function demoMultiAssertIdentityClosure(PDO $pdo): void
{
    $identityAccountIds = $pdo->query(<<<'SQL'
SELECT account_id FROM pa_tenant_member
UNION
SELECT account_id FROM pa_platform_operator
ORDER BY account_id
SQL)->fetchAll(PDO::FETCH_COLUMN);
    if ($identityAccountIds === []) {
        throw new RuntimeException('demo seed state has no installation identities');
    }
    $accountCount = (int)$pdo->query('SELECT COUNT(*) FROM pa_account')->fetchColumn();
    $activeAccountCount = (int)$pdo->query(
        "SELECT COUNT(*) FROM pa_account WHERE status = 'active'"
    )->fetchColumn();
    $credentialCount = (int)$pdo->query('SELECT COUNT(*) FROM pa_credential')->fetchColumn();
    $activeCredentialCount = (int)$pdo->query(<<<'SQL'
SELECT COUNT(*)
FROM pa_credential
WHERE kind = 'email_password' AND identifier_type = 'email' AND status = 'active'
SQL)->fetchColumn();
    if ($accountCount !== count($identityAccountIds)
        || $activeAccountCount !== $accountCount
        || $credentialCount !== $accountCount
        || $activeCredentialCount !== $accountCount) {
        throw new RuntimeException('demo seed state contains unexpected Accounts or Credentials');
    }
}

function demoMultiAssertSeedState(PDO $pdo): void
{
    $rows = $pdo->query('SELECT code, status FROM pa_tenant ORDER BY code FOR UPDATE')
        ->fetchAll(PDO::FETCH_ASSOC);
    $codes = array_column($rows, 'code');
    if ($codes !== ['default'] && $codes !== ['default', 'tenant-a', 'tenant-b']) {
        throw new RuntimeException('demo seed requires an exact fresh baseline or its completed retry state');
    }
    foreach ($rows as $row) {
        if ($row['code'] === 'default' && $row['status'] !== 'active') {
            throw new RuntimeException('fresh default Tenant is unavailable');
        }
    }
    if ($codes !== ['default']) {
        return;
    }

    $memberCount = (int)$pdo->query('SELECT COUNT(*) FROM pa_tenant_member')->fetchColumn();
    $platformCount = (int)$pdo->query('SELECT COUNT(*) FROM pa_platform_operator')->fetchColumn();
    $bindingCount = (int)$pdo->query('SELECT COUNT(*) FROM pa_tenant_entry_binding')->fetchColumn();
    $defaultOwnerCount = (int)$pdo->query(<<<'SQL'
SELECT COUNT(*)
FROM pa_tenant t
JOIN pa_tenant_member tm ON tm.tenant_id = t.id AND tm.status = 'active'
JOIN pa_member_role mr ON mr.tenant_id = tm.tenant_id AND mr.tenant_member_id = tm.id
JOIN pa_role r ON r.tenant_id = mr.tenant_id AND r.id = mr.role_id
WHERE t.code = 'default'
  AND r.`key` = 'core.tenant-owner' AND r.is_builtin = 1 AND r.status = 'active'
SQL)->fetchColumn();
    if ($memberCount !== 1 || $platformCount !== 1 || $bindingCount !== 0 || $defaultOwnerCount !== 1) {
        throw new RuntimeException('demo seed fresh baseline contains existing identities or Host bindings');
    }

    demoMultiAssertIdentityClosure($pdo);
}

function demoMultiAssertFinalState(
    PDO $pdo,
    PasswordHasher $passwords,
    string $tenantAEmail,
    string $tenantBEmail,
    string $sharedPassword,
    string $tenantAHost,
    string $tenantBHost
): void {
    $tenants = $pdo->query(
        'SELECT code, name, display_name, status FROM pa_tenant ORDER BY code'
    )->fetchAll(PDO::FETCH_ASSOC);
    $expectedTenants = [
        ['code' => 'default', 'status' => 'active'],
        ['code' => 'tenant-a', 'name' => 'Tenant A', 'display_name' => 'Tenant A', 'status' => 'active'],
        ['code' => 'tenant-b', 'name' => 'Tenant B', 'display_name' => 'Tenant B', 'status' => 'active'],
    ];
    if (count($tenants) !== count($expectedTenants)) {
        throw new RuntimeException('demo Tenant final state contains unexpected rows');
    }
    foreach ($expectedTenants as $index => $expected) {
        foreach ($expected as $field => $value) {
            if (($tenants[$index][$field] ?? null) !== $value) {
                throw new RuntimeException('demo Tenant final state does not match the plan');
            }
        }
    }

    $statement = $pdo->prepare(<<<'SQL'
SELECT t.code, c.identifier_normalized AS email, c.secret_hash
FROM pa_tenant t
JOIN pa_tenant_member tm ON tm.tenant_id = t.id AND tm.status = 'active'
JOIN pa_account a ON a.id = tm.account_id AND a.status = 'active'
JOIN pa_member_role mr ON mr.tenant_id = tm.tenant_id AND mr.tenant_member_id = tm.id
JOIN pa_role r ON r.tenant_id = mr.tenant_id AND r.id = mr.role_id
  AND r.`key` = 'core.tenant-owner' AND r.is_builtin = 1 AND r.status = 'active'
JOIN pa_credential c ON c.account_id = tm.account_id
  AND c.kind = 'email_password' AND c.identifier_type = 'email' AND c.status = 'active'
WHERE t.code IN ('tenant-a', 'tenant-b')
ORDER BY t.code, c.identifier_normalized
SQL);
    $statement->execute();
    $owners = $statement->fetchAll(PDO::FETCH_ASSOC);
    $expectedOwners = [
        "tenant-a\0{$tenantAEmail}",
        "tenant-b\0{$tenantAEmail}",
        "tenant-b\0{$tenantBEmail}",
    ];
    sort($expectedOwners, SORT_STRING);
    $actualOwners = array_map(
        static fn(array $row): string => $row['code'] . "\0" . $row['email'],
        $owners
    );
    if ($actualOwners !== $expectedOwners) {
        throw new RuntimeException('demo owner memberships do not provide the exact A/B selection model');
    }
    $memberCount = (int)$pdo->query('SELECT COUNT(*) FROM pa_tenant_member')->fetchColumn();
    $defaultOwnerCount = (int)$pdo->query(<<<'SQL'
SELECT COUNT(*)
FROM pa_tenant t
JOIN pa_tenant_member tm ON tm.tenant_id = t.id AND tm.status = 'active'
JOIN pa_member_role mr ON mr.tenant_id = tm.tenant_id AND mr.tenant_member_id = tm.id
JOIN pa_role r ON r.tenant_id = mr.tenant_id AND r.id = mr.role_id
WHERE t.code = 'default'
  AND r.`key` = 'core.tenant-owner' AND r.is_builtin = 1 AND r.status = 'active'
SQL)->fetchColumn();
    $demoMemberCount = (int)$pdo->query(<<<'SQL'
SELECT COUNT(*)
FROM pa_tenant_member tm
JOIN pa_tenant t ON t.id = tm.tenant_id
WHERE t.code IN ('tenant-a', 'tenant-b')
SQL)->fetchColumn();
    if ($memberCount !== 4 || $defaultOwnerCount !== 1 || $demoMemberCount !== 3) {
        throw new RuntimeException('demo Tenants contain unexpected membership rows');
    }
    $platformCount = (int)$pdo->query('SELECT COUNT(*) FROM pa_platform_operator')->fetchColumn();
    if ($platformCount !== 1) {
        throw new RuntimeException('demo seed final state contains unexpected PlatformOperators');
    }
    demoMultiAssertIdentityClosure($pdo);
    foreach ($owners as $owner) {
        if (!$passwords->verify($sharedPassword, (string)$owner['secret_hash'])) {
            throw new RuntimeException('published demo password does not match an owner credential');
        }
    }

    $binding = $pdo->prepare(<<<'SQL'
SELECT b.host, t.code, b.status
FROM pa_tenant_entry_binding b
JOIN pa_tenant t ON t.id = b.tenant_id
WHERE b.client_key = 'admin-web' AND b.host IN (?, ?)
ORDER BY b.host
SQL);
    $binding->execute([$tenantAHost, $tenantBHost]);
    $bindings = [];
    foreach ($binding->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $bindings[$row['host']] = [$row['code'], $row['status']];
    }
    $bindingCount = (int)$pdo->query('SELECT COUNT(*) FROM pa_tenant_entry_binding')->fetchColumn();
    if (($bindings[$tenantAHost] ?? null) !== ['tenant-a', 'active']
        || ($bindings[$tenantBHost] ?? null) !== ['tenant-b', 'active']
        || count($bindings) !== 2
        || $bindingCount !== 2) {
        throw new RuntimeException('demo Tenant Host bindings do not match the final plan');
    }
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
    $serverDir = dirname(__DIR__);
    loadCoreRuntime($serverDir);
    $tenantAHost = TenantEntryBindingResolver::normalizeHost(
        demoMultiRequired('PEANUT_DEMO_TENANT_A_HOST')
    );
    $tenantBHost = TenantEntryBindingResolver::normalizeHost(
        demoMultiRequired('PEANUT_DEMO_TENANT_B_HOST')
    );
    $reservedHosts = array_merge(
        demoMultiHostList('PLATFORM_HOSTS'),
        demoMultiHostList('TENANT_ADMIN_HOSTS')
    );
    if (hash_equals($tenantAHost, $tenantBHost)
        || in_array($tenantAHost, $reservedHosts, true)
        || in_array($tenantBHost, $reservedHosts, true)) {
        throw new RuntimeException('demo Tenant hosts must be distinct from Platform and shared Admin hosts');
    }

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

    $transactions = new PdoTransactionManager($pdo);
    $memberships = new PdoMembershipRepository($pdo);
    $passwords = new PasswordHasher();
    $bootstrap = new BootstrapService(
        $transactions,
        new PdoIdentityRepository($pdo),
        new PdoTenantRepository($pdo),
        $memberships,
        new PdoPlatformRepository($pdo),
        new PdoAuditRepository($pdo),
        $passwords
    );
    $adminProvisioner = new PdoTenantOwnerAdminProvisioner($pdo);
    [$tenantA, $tenantB] = $transactions->run(function () use (
        $pdo,
        $bootstrap,
        $adminProvisioner,
        $memberships,
        $passwords,
        $tenantAEmail,
        $tenantBEmail,
        $sharedPassword,
        $tenantAHost,
        $tenantBHost
    ): array {
        demoMultiAssertSeedState($pdo);
        $platforms = $pdo->query(
            "SELECT id, account_id FROM pa_platform_operator WHERE status = 'active' ORDER BY id LIMIT 2 FOR UPDATE"
        )->fetchAll(PDO::FETCH_ASSOC);
        if (count($platforms) !== 1) {
            throw new RuntimeException('demo seed requires exactly one active PlatformOperator');
        }
        $platformOperatorId = (int)$platforms[0]['id'];
        $tenantA = demoMultiTenant(
            $pdo,
            $bootstrap,
            $adminProvisioner,
            $platformOperatorId,
            'tenant-a',
            'Tenant A',
            $tenantAEmail,
            $sharedPassword,
            $passwords
        );
        $tenantB = demoMultiTenant(
            $pdo,
            $bootstrap,
            $adminProvisioner,
            $platformOperatorId,
            'tenant-b',
            'Tenant B',
            $tenantBEmail,
            $sharedPassword,
            $passwords
        );
        demoMultiEnsureSharedOwner(
            $memberships,
            $tenantB['tenant_id'],
            $tenantA['account_id'],
            $tenantB['role_id']
        );
        demoMultiBinding($pdo, $tenantA['tenant_id'], $tenantAHost);
        demoMultiBinding($pdo, $tenantB['tenant_id'], $tenantBHost);
        demoMultiAssertFinalState(
            $pdo,
            $passwords,
            $tenantAEmail,
            $tenantBEmail,
            $sharedPassword,
            $tenantAHost,
            $tenantBHost
        );
        return [$tenantA, $tenantB];
    });

    echo json_encode([
        'status' => 'applied',
        'tenant_a_id' => $tenantA['tenant_id'],
        'tenant_a_email' => $tenantAEmail,
        'tenant_b_id' => $tenantB['tenant_id'],
        'tenant_b_email' => $tenantBEmail,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
    return 0;
}

try {
    exit(demoMultiMain());
} catch (Throwable $exception) {
    demoMultiFail($exception->getMessage());
}
