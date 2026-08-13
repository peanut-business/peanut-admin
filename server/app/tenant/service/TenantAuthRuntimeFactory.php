<?php
declare(strict_types=1);

namespace app\tenant\service;

use PDO;
use PeanutAdmin\Kernel\Auth\Persistence\PdoTenantAuthRepository;
use PeanutAdmin\Kernel\Auth\SystemClock;
use PeanutAdmin\Kernel\Auth\TenantAuthService;
use PeanutAdmin\Kernel\Auth\TokenIssuer;
use PeanutAdmin\Kernel\Http\TenantAuthEndpoint;
use PeanutAdmin\Kernel\Identity\PasswordHasher;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTransactionManager;
use think\facade\Config;
use think\facade\Db;

final class TenantAuthRuntimeFactory
{
    private static ?TenantAuthEndpoint $endpoint = null;
    private static ?TenantAuthService $service = null;

    public static function endpoint(): TenantAuthEndpoint
    {
        if (self::$endpoint !== null) {
            return self::$endpoint;
        }
        return self::$endpoint = new TenantAuthEndpoint(self::service());
    }

    public static function service(): TenantAuthService
    {
        if (self::$service !== null) {
            return self::$service;
        }
        $key = trim((string)Config::get('tenant_auth.identifier_hmac_key', ''));
        if (strlen($key) < 32) {
            throw new \DomainException('TENANT_AUTH_CONFIGURATION_UNAVAILABLE');
        }
        $pdo = Db::connect()->connect();
        if (!$pdo instanceof PDO) {
            throw new \RuntimeException('TENANT_DATABASE_CONNECTION_UNAVAILABLE');
        }

        return self::$service = new TenantAuthService(
            new PdoTransactionManager($pdo),
            new PdoTenantAuthRepository($pdo),
            new PasswordHasher(),
            new SystemClock(),
            new TokenIssuer(),
            $key
        );
    }

    private function __construct()
    {
    }
}
