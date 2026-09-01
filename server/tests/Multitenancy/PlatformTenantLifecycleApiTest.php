<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/route/registry_source.php';

require dirname(__DIR__, 2) . '/bootstrap/environment.php';

use app\platform\identity\PlatformOperatorIdentity;
use app\platform\identity\PlatformOperatorIdentityPort;
use app\Modules\Official\Notification\Application\NotificationApplicationService;
use app\Modules\Official\Notification\Application\VerificationCodeService;
use app\common\execution\ExecutionContextStore;
use app\common\execution\CurrentExecutionContext;
use app\common\service\notice\NoticeSmsSender;
use app\common\service\tenant\TenantSettingsBootstrapRuntimeFactory;
use app\platform\service\ApplicationTenantBootstrapService;
use app\platform\service\TenantGovernanceService;
use app\platform\service\PdoTenantOwnerAdminProvisioner;
use app\platform\service\TenantOwnerAdminProvisioner;
use PeanutAdmin\Kernel\Identity\PasswordHasher;
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
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;
use PeanutAdmin\Kernel\Platform\Application\PlatformTenantAdminService;
use PeanutAdmin\Kernel\Platform\Bootstrap\BootstrapService;
use PeanutAdmin\Kernel\Tenancy\TenantStatus;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require __DIR__ . '/../Support/IsolatedBackendEnvironment.php';

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

final class LifecycleNoticeSmsSender implements NoticeSmsSender
{
    public function send(
        TenantContext|TenantSystemContext $context,
        string $mobile,
        string $templateId,
        array $variables,
        ?callable $beforeSend = null,
    ): array {
        throw new LogicException('lifecycle test does not send SMS');
    }
}

function lifecycleNotificationService(PDO $pdo, ExecutionContextStore $contexts): NotificationApplicationService
{
    $current = new CurrentExecutionContext($contexts);
    $access = new \app\common\execution\ExecutionContextAccess($current);
    return new NotificationApplicationService(
        $current,
        new VerificationCodeService(new LifecycleNoticeSmsSender(), new PdoTransactionManager($pdo), $access),
        $access,
    );
}

$host = IsolatedBackendEnvironment::required('DB_HOST');
$port = (int)IsolatedBackendEnvironment::required('DB_PORT');
$user = IsolatedBackendEnvironment::required('DB_USER');
$password = IsolatedBackendEnvironment::required('DB_PASS');
$admin = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    $user,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);
$database = IsolatedBackendEnvironment::required('DB_NAME');
$admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        $user,
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
    $pdo->exec(<<<'SQL'
INSERT INTO pa_tenant
  (code,name,display_name,status,activated_at,created_at,updated_at)
VALUES
  ('default','Default','Default','active',UTC_TIMESTAMP(3),UTC_TIMESTAMP(3),UTC_TIMESTAMP(3))
SQL);
    $applicationSchema = (string)file_get_contents(dirname(__DIR__, 2) . '/database/init.sql');
    lifecycleExpect($applicationSchema !== '', 'canonical application schema is missing');
    $pdo->exec($applicationSchema);
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
    $applicationContexts = new ExecutionContextStore();
    $service = new TenantGovernanceService(
        new LifecycleIdentity(new PlatformOperatorIdentity($platform->operatorId, $platform->accountId)),
        $transactions,
        $bootstrap,
        new PlatformTenantAdminService($pdo, $modules),
        new PdoTenantOwnerAdminProvisioner(
            $pdo,
            new ApplicationTenantBootstrapService(
                $pdo,
                lifecycleNotificationService($pdo, $applicationContexts),
                $applicationContexts,
                TenantSettingsBootstrapRuntimeFactory::forProvisioning($pdo),
            ),
        )
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
    lifecycleExpect((int)$pdo->query('SELECT COUNT(*) FROM pa_tenant')->fetchColumn() === 1, 'forged platform token wrote a Tenant');

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
    lifecycleExpect(
        (int)$pdo->query("SELECT COUNT(*) FROM pa_member_role WHERE tenant_id={$tenantId} AND tenant_member_id={$candidate['member_id']} AND role_id={$candidate['role_id']}")->fetchColumn() === 1,
        'owner membership does not point to the native Tenant role'
    );
    lifecycleExpect(
        (int)$pdo->query("SELECT COUNT(*) FROM pa_notice_scene WHERE tenant_id={$tenantId}")->fetchColumn() === 4,
        'provision did not run application-owned Tenant bootstrap'
    );

    $secondCandidate = $service->provision(
        'pm01-lifecycle-token',
        'beta-http',
        'Beta HTTP',
        'alpha-owner@example.test',
        null,
        'Alpha Owner',
        'pm01-http-second-tenant'
    );
    $secondTenantId = (int)$secondCandidate['tenant_id'];
    lifecycleExpect(
        (int)$secondCandidate['account_id'] === (int)$candidate['account_id'],
        'same owner email must reuse the Account across Tenants'
    );
    lifecycleExpect(
        (int)$secondCandidate['member_id'] !== (int)$candidate['member_id'],
        'same Account must receive a distinct TenantMember in each Tenant'
    );
    lifecycleExpect(
        (int)$pdo->query("SELECT COUNT(*) FROM pa_tenant_member WHERE tenant_id={$secondTenantId} AND account_id={$candidate['account_id']} AND id={$secondCandidate['member_id']}")->fetchColumn() === 1,
        'second Tenant owner membership is missing'
    );

    $tenantCount = (int)$pdo->query('SELECT COUNT(*) FROM pa_tenant')->fetchColumn();
    $memberCount = (int)$pdo->query('SELECT COUNT(*) FROM pa_tenant_member')->fetchColumn();
    $failingOwnerAdmins = new class($pdo) implements TenantOwnerAdminProvisioner {
        private PdoTenantOwnerAdminProvisioner $delegate;
        public function __construct(PDO $pdo) {
            $contexts = new ExecutionContextStore();
            $this->delegate = new PdoTenantOwnerAdminProvisioner(
                $pdo,
                new ApplicationTenantBootstrapService(
                    $pdo,
                    lifecycleNotificationService($pdo, $contexts),
                    $contexts,
                    TenantSettingsBootstrapRuntimeFactory::forProvisioning($pdo),
                ),
            );
        }
        public function provision(
            int $tenantId,
            int $accountId,
            int $memberId,
            int $coreRoleId,
            string $tenantCode,
            string $displayName
        ): int {
            $this->delegate->provision($tenantId, $accountId, $memberId, $coreRoleId, $tenantCode, $displayName);
            throw new RuntimeException('injected owner Admin failure');
        }
    };
    $failingService = new TenantGovernanceService(
        new LifecycleIdentity(new PlatformOperatorIdentity($platform->operatorId, $platform->accountId)),
        $transactions,
        $bootstrap,
        new PlatformTenantAdminService($pdo, $modules),
        $failingOwnerAdmins
    );
    lifecycleRejects(static fn() => $failingService->provision(
        'pm01-lifecycle-token',
        'rollback-http',
        'Rollback HTTP',
        'rollback-owner@example.test',
        'RollbackOwnerPassword2026',
        'Rollback Owner',
        'pm01-http-rollback'
    ));
    lifecycleExpect((int)$pdo->query('SELECT COUNT(*) FROM pa_tenant')->fetchColumn() === $tenantCount, 'owner provisioning failure left a partial Tenant');
    lifecycleExpect((int)$pdo->query('SELECT COUNT(*) FROM pa_tenant_member')->fetchColumn() === $memberCount, 'owner provisioning failure left a partial TenantMember');

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

    $route = peanut_route_registry_source(dirname(__DIR__, 2));
    lifecycleExpect(
        str_contains($route, "Route::post('tenants/provision'")
            && str_contains($route, "PlatformPermissionMiddleware::class, 'platform.tenant.provision-owner'")
            && str_contains($route, "Route::post('tenants/activate'")
            && str_contains($route, "Route::post('tenants/close'")
            && str_contains($route, "PlatformPermissionMiddleware::class, 'platform.tenant.lifecycle'"),
        'platform lifecycle HTTP routes lost their dedicated permissions'
    );
    $resendRoute = strpos($route, "Route::post('tenants/invitations/resend'");
    $inviteRoute = strpos($route, "Route::post('tenants/invitations'");
    lifecycleExpect(
        $resendRoute !== false && $inviteRoute !== false && $resendRoute < $inviteRoute,
        'specific invitation actions must precede the prefix-sensitive invitation collection route'
    );

    echo "PM01-PLATFORM-TENANT-LIFECYCLE-HTTP-001 passed\n";
} finally {
    $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
}
