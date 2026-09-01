<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/route/registry_source.php';

require dirname(__DIR__, 2) . '/bootstrap/environment.php';

use app\platform\service\PlatformOperatorSessionService;
use PeanutAdmin\Kernel\Auth\Persistence\PdoPlatformAuthRepository;
use PeanutAdmin\Kernel\Auth\PlatformAuthService;
use PeanutAdmin\Kernel\Auth\SystemClock;
use PeanutAdmin\Kernel\Auth\TokenIssuer;
use PeanutAdmin\Kernel\Authorization\CorePermissionCatalogSynchronizer;
use PeanutAdmin\Kernel\Authorization\Persistence\PdoAuthorizationCatalogRepository;
use PeanutAdmin\Kernel\Authorization\Persistence\Schema\AuthorizationSchema;
use PeanutAdmin\Kernel\Authorization\RevisionPermissionCache;
use PeanutAdmin\Kernel\Identity\PasswordHasher;
use PeanutAdmin\Kernel\Migration\ModuleSchema;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoAuditRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoIdentityRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoMembershipRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoPlatformRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTenantRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTransactionManager;
use PeanutAdmin\Kernel\Persistence\Schema\KernelSchema;
use PeanutAdmin\Kernel\Platform\Application\PlatformAccessAdminService;
use PeanutAdmin\Kernel\Platform\Authorization\PdoPlatformAuthorizationRepository;
use PeanutAdmin\Kernel\Platform\Authorization\PlatformAuthorizationEvaluator;
use PeanutAdmin\Kernel\Platform\Bootstrap\BootstrapService;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require __DIR__ . '/../Support/IsolatedBackendEnvironment.php';

function platformAccessHttpExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function platformAccessHttpSessions(PDO $pdo): PlatformOperatorSessionService
{
    $permissions = new PdoPlatformAuthorizationRepository($pdo);
    return new PlatformOperatorSessionService(
        new PlatformAuthService(
            new PdoTransactionManager($pdo),
            new PdoPlatformAuthRepository($pdo),
            new PasswordHasher(),
            new SystemClock(),
            new TokenIssuer(),
            str_repeat('a', 32)
        ),
        new PlatformAuthorizationEvaluator($permissions, new RevisionPermissionCache()),
        $permissions
    );
}

$serverRoot = dirname(__DIR__, 2);
$routes = peanut_route_registry_source($serverRoot);
$factory = (string)file_get_contents($serverRoot . '/app/platform/service/PlatformRuntimeFactory.php');
$controller = (string)file_get_contents($serverRoot . '/app/platform/controller/PlatformAccessController.php');

$expectedRoutes = [
    'operators/create' => ['createOperator', 'platform.operator.create'],
    'operators/update' => ['updateOperator', 'platform.operator.update'],
    'operators/roles/replace' => ['replaceOperatorRoles', 'platform.operator.role.assign'],
    'operators/activate' => ['activateOperator', 'platform.operator.lifecycle'],
    'operators/suspend' => ['suspendOperator', 'platform.operator.lifecycle'],
    'operators/close' => ['closeOperator', 'platform.operator.lifecycle'],
    'roles/create' => ['createRole', 'platform.role.create'],
    'roles/update' => ['updateRole', 'platform.role.update'],
    'roles/archive' => ['archiveRole', 'platform.role.archive'],
    'roles/permissions/replace' => ['replaceRolePermissions', 'platform.role.permission.assign'],
];
foreach ($expectedRoutes as $path => [$action, $permission]) {
    $pattern = sprintf(
        "~Route::post\\('%s', \\[PlatformAccessController::class, '%s'\\]\\)\\s*"
        . "->middleware\\(PlatformLoginMiddleware::class\\)\\s*"
        . "->middleware\\(PlatformPermissionMiddleware::class, '%s'\\);~",
        preg_quote($path, '~'),
        preg_quote($action, '~'),
        preg_quote($permission, '~')
    );
    platformAccessHttpExpect(
        preg_match($pattern, $routes) === 1,
        "{$path} lost its exact action or platform permission wiring"
    );
    platformAccessHttpExpect(
        str_contains($controller, "public function {$action}()"),
        "{$action} controller mutation is missing"
    );
}
platformAccessHttpExpect(
    str_contains($factory, 'public static function platformAccess(): PlatformAccessAdminService')
        && str_contains($factory, 'new PlatformAccessAdminService(self::pdo())'),
    'PlatformAccessAdminService factory wiring is missing'
);
platformAccessHttpExpect(
    str_contains($controller, '$this->platformContext->core')
        && str_contains($controller, '$context->operatorId')
        && str_contains($controller, '$context->accountId')
        && str_contains($controller, '$context->requestId')
        && str_contains($controller, 'catch (AdminAccessException $exception)')
        && str_contains($controller, '$exception->httpStatus * 100'),
    'controller lost trusted actor context or stable AdminAccessException mapping'
);

$host = IsolatedBackendEnvironment::required('DB_HOST');
$port = IsolatedBackendEnvironment::required('DB_PORT');
$user = IsolatedBackendEnvironment::required('DB_USER');
$password = IsolatedBackendEnvironment::required('DB_PASS');
$admin = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    $user,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);
$database = 'pa_pm01_access_http_' . strtolower(bin2hex(random_bytes(6)));
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
    foreach (ModuleSchema::tableNames() as $table) {
        $pdo->exec(ModuleSchema::createSql($table));
    }
    foreach (AuthorizationSchema::tableNames() as $table) {
        $pdo->exec(AuthorizationSchema::createSql($table));
    }
    (new CorePermissionCatalogSynchronizer(new PdoAuthorizationCatalogRepository($pdo)))->synchronize();

    $bootstrap = new BootstrapService(
        new PdoTransactionManager($pdo),
        new PdoIdentityRepository($pdo),
        new PdoTenantRepository($pdo),
        new PdoMembershipRepository($pdo),
        new PdoPlatformRepository($pdo),
        new PdoAuditRepository($pdo),
        new PasswordHasher()
    );
    $owner = $bootstrap->bootstrapPlatformOwner(
        'access-owner@example.test',
        'AccessOwnerPassword2026',
        'Access Owner',
        'pm01-access-http-bootstrap'
    );
    $created = (new PlatformAccessAdminService($pdo))->createOperator(
        $owner->operatorId,
        $owner->accountId,
        'access-scoped@example.test',
        'Access Scoped',
        'AccessScopedPassword2026',
        'pm01-access-http-create'
    );
    platformAccessHttpExpect($created['status'] === 'active', 'real operator create did not produce an active operator');

    $sessions = platformAccessHttpSessions($pdo);
    $login = $sessions->login(
        'access-scoped@example.test',
        'AccessScopedPassword2026',
        '127.0.0.2',
        'PM01 access HTTP fixture',
        'pm01-access-http-login'
    );
    $context = $sessions->context($login->tokens->access->expose(), 'pm01-access-http-context');
    try {
        $sessions->assertAllowed($context, 'platform.operator.create');
        throw new RuntimeException('operator without platform.operator.create unexpectedly passed authorization');
    } catch (Throwable $exception) {
        platformAccessHttpExpect(
            str_contains($exception->getMessage(), 'AUTHZ_PERMISSION_DENIED'),
            'permission denial changed shape'
        );
    }

    echo "PM01-PLATFORM-ACCESS-HTTP-WIRING-001 passed\n";
} finally {
    $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
}
