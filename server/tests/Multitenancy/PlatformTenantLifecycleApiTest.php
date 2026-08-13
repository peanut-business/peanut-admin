<?php
declare(strict_types=1);

use app\platform\identity\PlatformOperatorIdentity;
use app\platform\identity\PlatformOperatorIdentityPort;
use app\platform\service\TenantGovernanceService;
use PeanutAdmin\Kernel\Identity\PasswordHasher;
use PeanutAdmin\Kernel\Migration\ModuleSchema;
use PeanutAdmin\Kernel\Module\CompiledModuleRegistry;
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

function lifecycleExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function lifecycleRejects(Closure $operation): void
{
    try {
        $operation();
    } catch (Throwable) {
        return;
    }
    throw new RuntimeException('expected lifecycle rejection');
}

final readonly class LifecycleIdentity implements PlatformOperatorIdentityPort
{
    public function __construct(private PlatformOperatorIdentity $identity)
    {
    }

    public function requireActive(string $credential): PlatformOperatorIdentity
    {
        if (!hash_equals('pm01-lifecycle-token', $credential)) {
            throw new DomainException('PLATFORM_OPERATOR_AUTHENTICATION_FAILED');
        }
        return $this->identity;
    }
}

$host = getenv('DB_HOST') ?: getenv('MYSQL_HOST') ?: '127.0.0.1';
$port = (int)(getenv('DB_PORT') ?: getenv('MYSQL_PORT') ?: 3306);
$password = getenv('MYSQL_ROOT_PASSWORD') ?: getenv('DB_PASS') ?: 'peanut_admin_root_dev';
$admin = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    'root',
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);
$database = 'pa_pm01_http_' . strtolower(bin2hex(random_bytes(6)));
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

    $transactions = new PdoTransactionManager($pdo);
    $bootstrap = new BootstrapService(
        $transactions,
        new PdoIdentityRepository($pdo),
        new PdoTenantRepository($pdo),
        new PdoMembershipRepository($pdo),
        new PdoPlatformRepository($pdo),
        new PdoAuditRepository($pdo),
        new PasswordHasher()
    );
    $platform = $bootstrap->bootstrapPlatformOwner(
        'lifecycle@example.test',
        'LifecyclePassword2026',
        'Lifecycle Operator',
        'pm01-lifecycle-bootstrap'
    );
    $modules = new TenantModuleManager(
        new CompiledModuleRegistry([], [], [], [], 'pm01-lifecycle'),
        new PdoModuleRuntimeRepository($pdo),
        new class implements TenantModuleConfigValidator {
            public function assertValid(\PeanutAdmin\Kernel\Module\ManifestDocument $manifest, array $config): void
            {
                throw new DomainException('module mutation is outside the lifecycle slice');
            }
        }
    );
    $service = new TenantGovernanceService(
        new LifecycleIdentity(new PlatformOperatorIdentity($platform->operatorId, $platform->accountId)),
        $bootstrap,
        new PlatformTenantAdminService($pdo, $modules)
    );

    lifecycleRejects(static fn() => $service->provision(
        'forged-token',
        'forged',
        'Forged',
        'forged@example.test',
        'ForgedPassword2026',
        'Forged Owner',
        'pm01-forged'
    ));
    lifecycleExpect((int)$pdo->query('SELECT COUNT(*) FROM pa_tenant')->fetchColumn() === 0, 'forged platform token wrote a Tenant');

    $candidate = $service->provision(
        'pm01-lifecycle-token',
        'alpha-http',
        'Alpha HTTP',
        'alpha-owner@example.test',
        'AlphaOwnerPassword2026',
        'Alpha Owner',
        'pm01-http-provision'
    );
    $tenantId = (int)$candidate['tenant_id'];
    lifecycleExpect($candidate['status'] === 'pending', 'provision did not return owner candidate state');
    lifecycleExpect(
        $pdo->query("SELECT status FROM pa_tenant WHERE id={$tenantId}")->fetchColumn() === 'provisioning',
        'provision skipped the provisioning Tenant state'
    );
    lifecycleExpect(
        (int)$pdo->query("SELECT COUNT(*) FROM pa_tenant_member WHERE tenant_id={$tenantId} AND status='active'")->fetchColumn() === 1,
        'provision did not establish the single active first owner'
    );
    lifecycleExpect(
        (int)$pdo->query("SELECT COUNT(*) FROM pa_platform_audit_event WHERE request_id='pm01-http-provision'")->fetchColumn() === 1,
        'owner provisioning platform audit is missing'
    );
    lifecycleExpect(
        (int)$pdo->query("SELECT COUNT(*) FROM pa_tenant_audit_event WHERE tenant_id={$tenantId} AND request_id='pm01-http-provision:owner-activation'")->fetchColumn() === 1,
        'owner activation Tenant audit is missing'
    );

    lifecycleRejects(static fn() => $service->transition(
        'pm01-lifecycle-token',
        $tenantId,
        99,
        TenantStatus::Active,
        'wrong revision',
        'pm01-http-wrong-revision'
    ));
    lifecycleExpect(
        $pdo->query("SELECT status FROM pa_tenant WHERE id={$tenantId}")->fetchColumn() === 'provisioning',
        'revision mismatch changed Tenant state'
    );
    $active = $service->transition(
        'pm01-lifecycle-token',
        $tenantId,
        1,
        TenantStatus::Active,
        'owner ready',
        'pm01-http-activate'
    );
    lifecycleExpect($active['status'] === 'active' && (int)$active['revision'] === 2, 'Tenant activation result is incorrect');
    lifecycleExpect(
        (int)$pdo->query("SELECT COUNT(*) FROM pa_platform_audit_event WHERE request_id='pm01-http-activate' AND event_type='tenant.activated'")->fetchColumn() === 1,
        'Tenant activation platform audit is missing'
    );

    $closed = $service->transition(
        'pm01-lifecycle-token',
        $tenantId,
        (int)$active['revision'],
        TenantStatus::Closed,
        'customer instance retired',
        'pm01-http-close'
    );
    lifecycleExpect($closed['status'] === 'closed' && (int)$closed['revision'] === 3, 'Tenant closure result is incorrect');
    lifecycleExpect(
        $pdo->query("SELECT status FROM pa_tenant WHERE id={$tenantId}")->fetchColumn() === 'closed',
        'Tenant closure was not persisted'
    );
    lifecycleExpect(
        (int)$pdo->query("SELECT COUNT(*) FROM pa_platform_audit_event WHERE request_id='pm01-http-close' AND event_type='tenant.closed'")->fetchColumn() === 1,
        'Tenant closure platform audit is missing'
    );
    lifecycleRejects(static fn() => $service->transition(
        'pm01-lifecycle-token',
        $tenantId,
        (int)$closed['revision'],
        TenantStatus::Active,
        'closed tenants are terminal',
        'pm01-http-closed-reactivation'
    ));
    lifecycleExpect(
        $pdo->query("SELECT status FROM pa_tenant WHERE id={$tenantId}")->fetchColumn() === 'closed',
        'rejected closed Tenant transition changed persisted state'
    );

    $route = (string)file_get_contents(dirname(__DIR__, 2) . '/route/app.php');
    lifecycleExpect(
        str_contains($route, "Route::post('api/platform/tenants/provision'")
            && str_contains($route, "PlatformPermissionMiddleware::class, 'platform.tenant.provision-owner'")
            && str_contains($route, "Route::post('api/platform/tenants/activate'")
            && str_contains($route, "Route::post('api/platform/tenants/close'")
            && str_contains($route, "PlatformPermissionMiddleware::class, 'platform.tenant.lifecycle'"),
        'platform lifecycle HTTP routes lost their dedicated permissions'
    );

    echo "PM01-PLATFORM-TENANT-LIFECYCLE-HTTP-001 passed\n";
} finally {
    $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
}
