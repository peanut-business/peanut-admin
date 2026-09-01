#!/usr/bin/env php
<?php
declare(strict_types=1);

$repositoryRoot = dirname(__DIR__);
require_once $repositoryRoot . '/server/route/registry_source.php';
$routeSource = peanut_route_registry_source($repositoryRoot . '/server');
$accessConfig = require $repositoryRoot . '/server/config/admin_api_access.php';
$markdown = in_array('--markdown', $argv, true);
$inventoryJson = in_array('--inventory-json', $argv, true);

/** @return list<array{method:string,path:string,permission:string,access:string}> */
function adminApiMatrix(string $repositoryRoot, string $routeSource, array $accessConfig): array
{
    $groupStart = strpos($routeSource, "Route::group('api/admin'");
    $groupEnd = $groupStart === false
        ? false
        : strpos($routeSource, '})->middleware([LoginMiddleware::class, AuthMiddleware::class', $groupStart);
    if ($groupStart === false || $groupEnd === false) {
        throw new RuntimeException('api/admin route group or its authorization middleware chain is missing');
    }

    $group = substr($routeSource, $groupStart, $groupEnd - $groupStart);
    if (preg_match_all(
        "/Route::(get|post|put|delete|patch)\\(\\s*'([^']+)'/i",
        $group,
        $matches,
        PREG_SET_ORDER,
    ) === false) {
        throw new RuntimeException('unable to parse api/admin routes');
    }

    $permissionKeys = [];
    $sqlFiles = [$repositoryRoot . '/server/database/init.sql'];
    $sqlFiles = array_merge(
        $sqlFiles,
        glob($repositoryRoot . '/server/database/migrations/*.sql') ?: [],
        glob($repositoryRoot . '/server/app/Modules/*/*/Database/Migrations/*.sql') ?: [],
        glob($repositoryRoot . '/server/app/Modules/*/Database/Migrations/*.sql') ?: [],
    );
    foreach ($sqlFiles as $sqlFile) {
        $sql = (string)file_get_contents($sqlFile);
        preg_match_all("/['\"]([a-z0-9_.-]+(?:\\/[a-z0-9_.-]+)+)['\"]/i", $sql, $permissions);
        foreach ($permissions[1] as $permission) {
            $permissionKeys[strtolower($permission)] = true;
        }
    }

    $authenticated = array_fill_keys(normalizedExceptionRoutes($accessConfig['authenticated'] ?? []), true);
    $matrix = [];
    foreach ($matches as $match) {
        $method = strtoupper($match[1]);
        $path = 'api/admin/' . strtolower(trim($match[2], '/'));
        $permission = substr($path, strlen('api/admin/'));
        $routeKey = $method . ' ' . $path;
        $access = isset($authenticated[$routeKey])
            ? 'authenticated'
            : (isset($permissionKeys[$permission]) ? 'permission' : 'unregistered');
        $matrix[] = compact('method', 'path', 'permission', 'access');
    }
    return $matrix;
}

/** @return list<string> */
function normalizedExceptionRoutes(mixed $routes): array
{
    if (!is_array($routes)) {
        throw new RuntimeException('admin API exception metadata must be an array');
    }
    $normalized = [];
    foreach ($routes as $route) {
        if (preg_match('/^([A-Z]+)\s+(.+)$/i', trim((string)$route), $match) !== 1) {
            throw new RuntimeException('invalid admin API exception route: ' . (string)$route);
        }
        $normalized[] = strtoupper($match[1]) . ' ' . strtolower(trim($match[2], '/'));
    }
    return array_values(array_unique($normalized));
}

/** @return array<string,true> */
function explicitRoutes(string $routeSource): array
{
    preg_match_all(
        "/Route::(get|post|put|delete|patch)\\(\\s*'([^']+)'/i",
        $routeSource,
        $matches,
        PREG_SET_ORDER,
    );
    $routes = [];
    foreach ($matches as $match) {
        $path = strtolower(trim($match[2], '/'));
        if (!str_starts_with($path, 'api/admin/')) {
            $routes[strtoupper($match[1]) . ' ' . $path] = true;
        }
    }
    return $routes;
}

try {
    if ($markdown && $inventoryJson) {
        throw new RuntimeException('--markdown and --inventory-json cannot be combined');
    }
    if (($accessConfig['version'] ?? null) !== 1) {
        throw new RuntimeException('admin API exception metadata version must be exactly 1');
    }
    $public = normalizedExceptionRoutes($accessConfig['public'] ?? []);
    $authenticated = normalizedExceptionRoutes($accessConfig['authenticated'] ?? []);
    $overlap = array_values(array_intersect($public, $authenticated));
    if ($overlap !== []) {
        throw new RuntimeException('routes cannot be both public and authenticated: ' . implode(', ', $overlap));
    }
    $platformPublic = normalizedExceptionRoutes($accessConfig['platform_public'] ?? []);
    $platformAuthenticated = normalizedExceptionRoutes($accessConfig['platform_authenticated'] ?? []);
    $platformOverlap = array_values(array_intersect($platformPublic, $platformAuthenticated));
    if ($platformOverlap !== []) {
        throw new RuntimeException('Platform routes cannot be both public and authenticated: ' . implode(', ', $platformOverlap));
    }
    foreach (array_merge($public, $authenticated) as $exception) {
        if (str_contains($exception, ' api/platform/')) {
            throw new RuntimeException('platform routes cannot use Tenant Admin metadata: ' . $exception);
        }
    }
    foreach (array_merge($platformPublic, $platformAuthenticated) as $exception) {
        if (!str_contains($exception, ' api/platform/')) {
            throw new RuntimeException('non-platform route cannot use Platform metadata: ' . $exception);
        }
    }

    preg_match_all(
        "/Route::(get|post|put|delete|patch)\\(\\s*'(api\\/platform\\/[^']+)'/i",
        $routeSource,
        $platformMatches,
        PREG_SET_ORDER,
    );
    $platformExceptions = array_fill_keys(array_merge($platformPublic, $platformAuthenticated), true);
    foreach ($platformMatches as $platformRoute) {
        $method = strtoupper($platformRoute[1]);
        $path = strtolower(trim($platformRoute[2], '/'));
        $routeKey = $method . ' ' . $path;
        if (isset($platformExceptions[$routeKey])) {
            continue;
        }
        $offset = strpos($routeSource, $platformRoute[0]);
        $nextRoute = $offset === false ? false : strpos($routeSource, 'Route::', $offset + strlen($platformRoute[0]));
        $statement = $offset === false
            ? ''
            : substr($routeSource, $offset, ($nextRoute === false ? strlen($routeSource) : $nextRoute) - $offset);
        if (!str_contains($statement, 'PlatformLoginMiddleware::class')
            || !str_contains($statement, 'PlatformPermissionMiddleware::class')) {
            throw new RuntimeException('Platform route lacks exact permission metadata: ' . $routeKey);
        }
    }

    $matrix = adminApiMatrix($repositoryRoot, $routeSource, $accessConfig);
    $known = [];
    foreach ($matrix as $row) {
        $known[$row['method'] . ' ' . $row['path']] = true;
    }
    $known += explicitRoutes($routeSource);
    foreach (array_merge($public, $authenticated, $platformPublic, $platformAuthenticated) as $exception) {
        if (!isset($known[$exception])) {
            throw new RuntimeException('exception metadata does not match an exact route: ' . $exception);
        }
    }

    $unregistered = array_values(array_filter(
        $matrix,
        static fn(array $row): bool => $row['access'] === 'unregistered',
    ));

    if ($markdown) {
        echo "| Method | Admin API route | Access registration |\n";
        echo "|---|---|---|\n";
        foreach ($matrix as $row) {
            $registration = $row['access'] === 'permission'
                ? '`' . $row['permission'] . '`'
                : $row['access'];
            echo sprintf("| %s | `%s` | %s |\n", $row['method'], $row['path'], $registration);
        }
    }

    if ($unregistered !== []) {
        foreach ($unregistered as $row) {
            fwrite(STDERR, sprintf(
                "UNREGISTERED: %s %s (expected permission %s or authenticated metadata)\n",
                $row['method'],
                $row['path'],
                $row['permission'],
            ));
        }
        exit(1);
    }

    $permissionCount = count(array_filter(
        $matrix,
        static fn(array $row): bool => $row['access'] === 'permission',
    ));
    $authenticatedCount = count($matrix) - $permissionCount;
    if ($inventoryJson) {
        echo json_encode(
            peanut_route_endpoint_inventory($repositoryRoot . '/server'),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ) . PHP_EOL;
    } else {
        echo sprintf(
            "Admin API permission gate passed: %d routes (%d permission, %d authenticated)\n",
            count($matrix),
            $permissionCount,
            $authenticatedCount,
        );
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Admin API permission gate failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
