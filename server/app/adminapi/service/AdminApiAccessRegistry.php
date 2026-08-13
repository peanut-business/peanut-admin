<?php
declare(strict_types=1);

namespace app\adminapi\service;

use think\facade\Config;

/** Exact, method-aware exceptions to Tenant Admin API default-deny RBAC. */
final class AdminApiAccessRegistry
{
    public static function isAuthenticatedOnly(string $method, string $path): bool
    {
        return in_array(self::routeKey($method, $path), self::authenticatedRoutes(), true);
    }

    public static function isPublic(string $method, string $path): bool
    {
        return in_array(self::routeKey($method, $path), self::publicRoutes(), true);
    }

    public static function isPlatformPublic(string $method, string $path): bool
    {
        return in_array(self::routeKey($method, $path), self::routes('platform_public'), true);
    }

    public static function isPlatformAuthenticatedOnly(string $method, string $path): bool
    {
        return in_array(self::routeKey($method, $path), self::routes('platform_authenticated'), true);
    }

    /** @return list<string> */
    public static function authenticatedRoutes(): array
    {
        return self::routes('authenticated');
    }

    /** @return list<string> */
    public static function publicRoutes(): array
    {
        return self::routes('public');
    }

    public static function version(): int
    {
        return (int)Config::get('admin_api_access.version', 0);
    }

    private static function routeKey(string $method, string $path): string
    {
        return strtoupper(trim($method)) . ' ' . strtolower(trim($path, '/'));
    }

    /** @return list<string> */
    private static function routes(string $kind): array
    {
        $routes = Config::get('admin_api_access.' . $kind, []);
        if (!is_array($routes)) {
            return [];
        }

        return array_values(array_unique(array_map(
            static fn(mixed $route): string => self::normalizeConfiguredRoute((string)$route),
            $routes,
        )));
    }

    private static function normalizeConfiguredRoute(string $route): string
    {
        if (preg_match('/^([A-Z]+)\s+(.+)$/i', trim($route), $matches) !== 1) {
            return '';
        }
        return self::routeKey($matches[1], $matches[2]);
    }
}
