<?php
declare(strict_types=1);

use app\common\service\tenant\TenantAvailabilityGuard;
use app\platform\identity\PlatformOperatorIdentity;
use app\platform\identity\PlatformOperatorIdentityPort;
use app\platform\identity\UnavailablePlatformOperatorIdentityPort;
use app\platform\service\TenantGovernanceService;
use app\platform\service\TenantOwnerAdminProvisioner;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;
use PeanutAdmin\Kernel\Identity\PasswordHasher;
use PeanutAdmin\Kernel\Migration\ModuleSchema;
use PeanutAdmin\Kernel\Module\CompiledModuleRegistry;
use PeanutAdmin\Kernel\Module\ManifestDocument;
use PeanutAdmin\Kernel\Module\ModuleException;
use PeanutAdmin\Kernel\Module\Persistence\PdoModuleRuntimeRepository;
use PeanutAdmin\Kernel\Module\TenantModuleConfigValidator;
use PeanutAdmin\Kernel\Module\TenantModuleManager;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoAuditRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoIdentityRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoMembershipRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoPlatformRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTenantRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTransactionManager;
use PeanutAdmin\Kernel\Persistence\Schema\KernelSchema;
use PeanutAdmin\Kernel\Platform\Application\PlatformTenantAdminService;
use PeanutAdmin\Kernel\Platform\Bootstrap\BootstrapService;
use PeanutAdmin\Kernel\Tenancy\TenantStatus;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function pm01Expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function pm01Rejected(Closure $operation, string $expectedMessage): void
{
    try {
        $operation();
    } catch (Throwable $exception) {
        pm01Expect(
            str_contains($exception->getMessage(), $expectedMessage),
            "unexpected rejection: {$exception->getMessage()}"
        );
        return;
    }
    throw new RuntimeException("expected rejection: {$expectedMessage}");
}

final readonly class Pm01FixtureIdentity implements PlatformOperatorIdentityPort
{
    public function __construct(private PlatformOperatorIdentity $identity)
    {
    }

    public function requireActive(string $credential): PlatformOperatorIdentity
    {
        if (!hash_equals('fixture-platform-credential', $credential)) {
            throw new DomainException('PLATFORM_OPERATOR_AUTHENTICATION_FAILED');
        }
        return $this->identity;
    }
}

final class Pm01FixtureConfigValidator implements TenantModuleConfigValidator
{
    public function assertValid(ManifestDocument $manifest, array $config): void
    {
        if (array_keys($config) !== ['region'] || !is_string($config['region']) || $config['region'] === '') {
            throw new ModuleException('MODULE_CONFIG_INVALID', 'region is required');
        }
    }
}

function pm01Bootstrap(PDO $pdo): BootstrapService
{
    return new BootstrapService(
        new PdoTransactionManager($pdo),
        new PdoIdentityRepository($pdo),
        new PdoTenantRepository($pdo),
        new PdoMembershipRepository($pdo),
        new PdoPlatformRepository($pdo),
        new PdoAuditRepository($pdo),
        new PasswordHasher()
    );
}

$host = getenv('MYSQL_HOST') ?: '127.0.0.1';
$port = getenv('MYSQL_PORT') ?: '33463';
$password = getenv('MYSQL_ROOT_PASSWORD') ?: 'peanut_admin_root_dev';
$admin = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    'root',
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);
$database = 'pa_pm01_' . strtolower(bin2hex(random_bytes(6)));
$admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        'root',
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    foreach (KernelSchema::tableNames() as $table) {
        $pdo->exec(KernelSchema::createSql($table));
    }
    $pdo->exec(KernelSchema::addTenantMemberDepartmentForeignKeySql());
    foreach (ModuleSchema::tableNames() as $table) {
        $pdo->exec(ModuleSchema::createSql($table));
    }

    $bootstrap = pm01Bootstrap($pdo);
    $platform = $bootstrap->bootstrapPlatformOwner(
        'operator@example.test',
        'OperatorPassword2026',
        'Fixture Operator',
        'pm01-platform-bootstrap'
    );
    $manifest = ManifestDocument::fromArray('/fixture/pm01', [
        'key' => 'peanut.fixture-governance',
        'tenant' => ['requires' => []],
    ]);
    $registry = new CompiledModuleRegistry([$manifest], [], [], [], $manifest->digest);
    $moduleRepository = new PdoModuleRuntimeRepository($pdo);
    $validator = new Pm01FixtureConfigValidator();
    $modules = new TenantModuleManager($registry, $moduleRepository, $validator);
    $administration = new PlatformTenantAdminService($pdo, $modules);
    $transactions = new PdoTransactionManager($pdo);
    $ownerAdmins = new class implements TenantOwnerAdminProvisioner {
        public function provision(
            int $tenantId,
            int $accountId,
            int $memberId,
            int $coreRoleId,
            string $tenantCode,
            string $displayName
        ): int {
            return 1;
        }
    };
    $identity = new PlatformOperatorIdentity($platform->operatorId, $platform->accountId);
    $governance = new TenantGovernanceService(
        new Pm01FixtureIdentity($identity),
        $transactions,
        $bootstrap,
        $administration,
        $ownerAdmins
    );

    $failClosed = new TenantGovernanceService(
        new UnavailablePlatformOperatorIdentityPort(),
        $transactions,
        $bootstrap,
        $administration,
        $ownerAdmins
    );
    pm01Rejected(
        static fn() => $failClosed->provision(
            '', 'forged', 'Forged', 'forged@example.test', 'ForgedPassword2026', 'Forged', 'pm01-forged'
        ),
        'PLATFORM_OPERATOR_AUTHENTICATION_UNAVAILABLE'
    );
    pm01Expect((int)$pdo->query('SELECT COUNT(*) FROM pa_tenant')->fetchColumn() === 0, 'fail-closed identity wrote a tenant');

    $candidate = $governance->provision(
        'fixture-platform-credential',
        'alpha',
        'Alpha Tenant',
        'owner@example.test',
        'OwnerPassword2026',
        'Alpha Owner',
        'pm01-provision'
    );
    $tenantId = $candidate['tenant_id'];
    pm01Expect($candidate['status'] === 'pending', 'provision must return the Core owner candidate state');
    pm01Expect(
        $pdo->query("SELECT status FROM pa_tenant WHERE id={$tenantId}")->fetchColumn() === 'provisioning',
        'tenant must remain provisioning until an explicit lifecycle transition'
    );
    pm01Expect(
        (int)$pdo->query("SELECT COUNT(*) FROM pa_tenant_member WHERE tenant_id={$tenantId} AND status='active'")->fetchColumn() === 1,
        'first owner must be active before tenant activation'
    );

    $active = $governance->transition(
        'fixture-platform-credential', $tenantId, 1, TenantStatus::Active, 'provisioning complete', 'pm01-active'
    );
    pm01Expect($active['status'] === 'active', 'provisioning tenant did not activate');

    $pdo->prepare(<<<'SQL'
INSERT INTO pa_module_installation (
    module_key, installed_version, manifest_schema_version, manifest_digest,
    status, installed_at, activated_at, created_at, updated_at
) VALUES (?, '1.0.0', 1, ?, 'active', CURRENT_TIMESTAMP(3), CURRENT_TIMESTAMP(3), CURRENT_TIMESTAMP(3), CURRENT_TIMESTAMP(3))
SQL)->execute(['peanut.fixture-governance', $manifest->digest]);

    pm01Rejected(
        static fn() => $governance->enableModule(
            'fixture-platform-credential', $tenantId, 'peanut.fixture-governance', [], 'manual', null, null,
            'invalid config fixture', 'pm01-module-invalid'
        ),
        'region is required'
    );
    pm01Expect((int)$pdo->query('SELECT COUNT(*) FROM pa_tenant_module')->fetchColumn() === 0, 'invalid module config was persisted');

    $enabled = $governance->enableModule(
        'fixture-platform-credential', $tenantId, 'peanut.fixture-governance', ['region' => 'cn-east'],
        'manual', null, null, 'enable fixture module', 'pm01-module-enable'
    );
    pm01Expect($enabled['status'] === 'enabled', 'valid module configuration was not enabled');

    $tenantRepository = new PdoTenantRepository($pdo);
    $guard = new TenantAvailabilityGuard($tenantRepository);
    $context = TenantContext::fromValidatedSession(
        new ValidatedTenantSession(
            1, 'fixture-session', $tenantId, $candidate['account_id'], $candidate['member_id'],
            'admin-web', new DateTimeImmutable('2026-08-12T00:00:00Z'), 1
        ),
        'pm01-business-write'
    );
    $guard->assertNewSessionAllowed($tenantId);
    $guard->assertBusinessWriteAllowed($context);

    $revision = (int)$pdo->query("SELECT revision FROM pa_tenant WHERE id={$tenantId}")->fetchColumn();
    $suspended = $governance->transition(
        'fixture-platform-credential', $tenantId, $revision, TenantStatus::Suspended, 'support hold', 'pm01-suspend'
    );
    pm01Expect($suspended['status'] === 'suspended', 'active tenant did not suspend');
    pm01Rejected(static fn() => $guard->assertNewSessionAllowed($tenantId), 'TENANT_UNAVAILABLE');
    pm01Rejected(static fn() => $guard->assertBusinessWriteAllowed($context), 'TENANT_UNAVAILABLE');
    pm01Rejected(
        static fn() => $governance->enableModule(
            'fixture-platform-credential', $tenantId, 'peanut.fixture-governance', ['region' => 'cn-west'],
            'manual', null, null, 'suspended write', 'pm01-module-suspended'
        ),
        'Only an active tenant'
    );

    $reactivated = $governance->transition(
        'fixture-platform-credential', $tenantId, (int)$suspended['revision'], TenantStatus::Active,
        'hold cleared', 'pm01-reactivate'
    );
    $closed = $governance->transition(
        'fixture-platform-credential', $tenantId, (int)$reactivated['revision'], TenantStatus::Closed,
        'customer closure', 'pm01-close'
    );
    pm01Expect($closed['status'] === 'closed', 'tenant did not close');
    pm01Rejected(
        static fn() => $governance->transition(
            'fixture-platform-credential', $tenantId, (int)$closed['revision'], TenantStatus::Active,
            'forbidden reopen', 'pm01-reopen'
        ),
        'Tenant cannot transition from closed to active'
    );
    pm01Rejected(static fn() => $guard->assertNewSessionAllowed($tenantId), 'TENANT_UNAVAILABLE');
    pm01Expect(
        (int)$pdo->query("SELECT COUNT(*) FROM pa_tenant_audit_event WHERE tenant_id={$tenantId}")->fetchColumn() >= 5,
        'Core tenant governance audit evidence is incomplete'
    );

    echo "PM01-TENANT-GOVERNANCE-001 passed\n";
} finally {
    $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
}
