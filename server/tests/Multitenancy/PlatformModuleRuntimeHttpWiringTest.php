<?php
declare(strict_types=1);

use PeanutAdmin\Kernel\Auth\ValidatedPlatformSession;
use PeanutAdmin\Kernel\Authorization\AuthorizationException;
use PeanutAdmin\Kernel\Authorization\CorePermissionCatalog;
use PeanutAdmin\Kernel\Authorization\CorePermissionCatalogSynchronizer;
use PeanutAdmin\Kernel\Authorization\Persistence\PdoAuthorizationCatalogRepository;
use PeanutAdmin\Kernel\Authorization\RevisionPermissionCache;
use PeanutAdmin\Kernel\Context\PlatformContext;
use PeanutAdmin\Kernel\Platform\Authorization\PdoPlatformAuthorizationRepository;
use PeanutAdmin\Kernel\Platform\Authorization\PlatformAuthorizationEvaluator;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/database/install.php';

function platformModuleHttpExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function platformModuleHttpDenied(
    PlatformAuthorizationEvaluator $evaluator,
    PlatformContext $context,
    string $permission,
): void {
    try {
        $evaluator->assertAllowed($context, $permission);
    } catch (AuthorizationException) {
        return;
    }
    throw new RuntimeException("permission unexpectedly allowed: {$permission}");
}

function platformModuleHttpContext(int $operatorId, int $accountId, string $sessionKey): PlatformContext
{
    return PlatformContext::fromValidatedSession(new ValidatedPlatformSession(
        $operatorId,
        $sessionKey,
        $operatorId,
        $accountId,
        'platform-web',
        new DateTimeImmutable('+1 hour'),
    ));
}

$serverRoot = dirname(__DIR__, 2);
$routeSource = (string)file_get_contents($serverRoot . '/route/app.php');
$controllerSource = (string)file_get_contents($serverRoot . '/app/platform/controller/PlatformModuleLifecycleController.php');
$middlewareSource = (string)file_get_contents($serverRoot . '/app/platform/http/middleware/PlatformInstanceToolMiddleware.php');
$serviceSource = (string)file_get_contents($serverRoot . '/app/platform/service/plugin/PlatformModuleRuntimeService.php');
$commandSource = (string)file_get_contents($serverRoot . '/app/command/ModuleSync.php');
$consoleSource = (string)file_get_contents($serverRoot . '/config/console.php');
$moduleConfigSource = (string)file_get_contents($serverRoot . '/config/modules.php');
$webRouteSource = (string)file_get_contents(dirname($serverRoot) . '/web/src/router/routes/modules/dev-tools.ts');
$webApiSource = (string)file_get_contents(dirname($serverRoot) . '/web/src/api/dev-tools/modules.ts');

$routes = [
    ['get', 'api/platform/instance-tools/modules', 'lists', 'platform.module.read'],
    ['post', 'api/platform/instance-tools/modules/install', 'install', 'platform.module.install'],
    ['post', 'api/platform/instance-tools/modules/uninstall', 'uninstall', 'platform.module.uninstall'],
    ['post', 'api/platform/instance-tools/modules/disable', 'disable', 'platform.module.disable'],
    ['post', 'api/platform/instance-tools/modules/sync', 'sync', 'platform.module.sync'],
];
$permissionKeys = [];
foreach ($routes as [$method, $path, $action, $permission]) {
    $permissionKeys[] = $permission;
    $pattern = sprintf(
        "~Route::%s\\('%s', \\[PlatformModuleLifecycleController::class, '%s'\\]\\)\\s*"
        . "->middleware\\(PlatformLoginMiddleware::class\\)\\s*"
        . "->middleware\\(PlatformPermissionMiddleware::class, '%s'\\)\\s*"
        . "->middleware\\(PlatformInstanceToolMiddleware::class\\);~",
        $method,
        preg_quote($path, '~'),
        preg_quote($action, '~'),
        preg_quote($permission, '~'),
    );
    platformModuleHttpExpect(preg_match($pattern, $routeSource) === 1, "{$path} lost its exact Platform middleware chain");
    platformModuleHttpExpect(str_contains($controllerSource, "public function {$action}()"), "{$action} controller adapter is missing");
}
sort($permissionKeys, SORT_STRING);
platformModuleHttpExpect(
    !str_contains($routeSource, "api/admin/core.module")
        && !str_contains($controllerSource . $serviceSource, 'AdminPermissionService')
        && !str_contains($controllerSource . $serviceSource, 'TenantAuthorizationEvaluator')
        && !str_contains($controllerSource . $serviceSource, 'TenantContext'),
    'instance package lifecycle crossed into Tenant Admin authorization',
);
platformModuleHttpExpect(
    str_contains($middlewareSource, "env('APP_ENV', '')")
        && str_contains($middlewareSource, "!== 'development'")
        && str_contains($middlewareSource, '!app()->isDebug()')
        && str_contains($middlewareSource, 'InstanceToolAccessGuard::fromConfiguredValue')
        && str_contains($middlewareSource, 'MODULE_RUNTIME_MUTATION_DISABLED'),
    'instance-tool environment/deployment gate is incomplete',
);
platformModuleHttpExpect(
    str_contains($webRouteSource, "path: 'modules'")
        && str_contains($webRouteSource, "instanceTool: true")
        && str_contains($webApiSource, "const PLATFORM_TOKEN_KEY = 'peanut-platform-token'")
        && !str_contains($webApiSource, 'peanut-admin-token'),
    'Admin Web module page lost its existing /dev-tools plane or independent Platform token',
);
platformModuleHttpExpect(
    str_contains($consoleSource, "'module:sync'")
        && str_contains($commandSource, "setName('module:sync')")
        && str_contains($commandSource, "addOption('module'")
        && !str_contains($moduleConfigSource, 'frontend_components'),
    'module:sync command contract or module.json-derived frontend catalog regressed',
);

$host = getenv('DB_HOST') ?: '';
$port = getenv('DB_PORT') ?: '';
$database = getenv('DB_NAME') ?: '';
$user = getenv('DB_USER') ?: '';
$password = getenv('DB_PASS') ?: '';
platformModuleHttpExpect($host !== '' && $port !== '' && $database !== '' && $user !== '', 'registered database environment is required');
$pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
    $user,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false],
);
platformModuleHttpExpect((int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn() === 0, 'Platform Module HTTP test database must start empty');
$identity = initializeCoreIdentity($pdo, 'module-http-owner@example.test', 'ModuleHttpOwnerPassword2026', null);
executeSqlFiles($pdo, [$serverRoot . '/database/init.sql']);
(new CorePermissionCatalogSynchronizer(new PdoAuthorizationCatalogRepository($pdo)))->synchronize();

$catalogKeys = CorePermissionCatalog::PLATFORM;
sort($catalogKeys, SORT_STRING);
platformModuleHttpExpect(
    array_values(array_intersect($catalogKeys, $permissionKeys)) === $permissionKeys,
    'CorePermissionCatalog::PLATFORM is missing a Platform Module route key',
);
$placeholders = implode(',', array_fill(0, count($permissionKeys), '?'));
$registered = $pdo->prepare("SELECT `key` FROM pa_permission WHERE module_key='platform' AND status='active' AND `key` IN ({$placeholders}) ORDER BY `key`");
$registered->execute($permissionKeys);
platformModuleHttpExpect($registered->fetchAll(PDO::FETCH_COLUMN) === $permissionKeys, 'Platform Module route keys did not synchronize as active platform permissions');

$now = '2026-08-26 00:00:00.000';
$pdo->exec("INSERT INTO pa_account (display_name,status,created_at,updated_at) VALUES ('Module HTTP Scoped','active','{$now}','{$now}')");
$scopedAccountId = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO pa_platform_operator (account_id,display_name,status,created_at,updated_at) VALUES ({$scopedAccountId},'Module HTTP Scoped','active','{$now}','{$now}')");
$scopedOperatorId = (int)$pdo->lastInsertId();
$scopedContext = platformModuleHttpContext($scopedOperatorId, $scopedAccountId, 'module-http-scoped');
$repository = new PdoPlatformAuthorizationRepository($pdo);
$evaluator = new PlatformAuthorizationEvaluator($repository, new RevisionPermissionCache());
foreach ($permissionKeys as $permission) platformModuleHttpDenied($evaluator, $scopedContext, $permission);

$pdo->exec("INSERT INTO pa_platform_role (`key`,name,is_builtin,status,created_at,updated_at) VALUES ('platform.module-runtime-operator','Module Runtime Operator',0,'active','{$now}','{$now}')");
$roleId = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO pa_platform_operator_role (platform_operator_id,platform_role_id,assigned_at) VALUES ({$scopedOperatorId},{$roleId},'{$now}')");
$permissionIds = $pdo->prepare("SELECT id FROM pa_permission WHERE `key` IN ({$placeholders}) ORDER BY `key`");
$permissionIds->execute($permissionKeys);
$bind = $pdo->prepare('INSERT INTO pa_platform_role_permission (platform_role_id,permission_id,granted_at) VALUES (?,?,?)');
foreach ($permissionIds->fetchAll(PDO::FETCH_COLUMN) as $permissionId) $bind->execute([$roleId, (int)$permissionId, $now]);
$evaluator = new PlatformAuthorizationEvaluator($repository, new RevisionPermissionCache());
foreach ($permissionKeys as $permission) $evaluator->assertAllowed($scopedContext, $permission);

$ownerAccountId = (int)$pdo->query('SELECT account_id FROM pa_platform_operator WHERE id=' . (int)$identity['operator_id'])->fetchColumn();
$ownerContext = platformModuleHttpContext((int)$identity['operator_id'], $ownerAccountId, 'module-http-owner');
platformModuleHttpDenied(
    new PlatformAuthorizationEvaluator($repository, new RevisionPermissionCache()),
    $ownerContext,
    'platform.module.unregistered-probe',
);

echo "PLATFORM-MODULE-RUNTIME-HTTP-D3-001 passed\n";
