<?php
declare(strict_types=1);

namespace app\platform\service;

use app\platform\identity\CorePlatformOperatorIdentityPort;
use app\platform\identity\PlatformOperatorAccountBoundary;
use PDO;
use PeanutAdmin\Kernel\Auth\Persistence\PdoPlatformAuthRepository;
use PeanutAdmin\Kernel\Auth\PlatformAuthService;
use PeanutAdmin\Kernel\Auth\SystemClock;
use PeanutAdmin\Kernel\Auth\TokenIssuer;
use PeanutAdmin\Kernel\Authorization\RevisionPermissionCache;
use PeanutAdmin\Kernel\Identity\PasswordHasher;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTransactionManager;
use PeanutAdmin\Kernel\Platform\Authorization\PdoPlatformAuthorizationRepository;
use PeanutAdmin\Kernel\Platform\Authorization\PlatformAuthorizationEvaluator;
use think\facade\Config;
use think\facade\Db;

final class PlatformRuntimeFactory
{
    private static ?PlatformOperatorSessionService $sessions = null;

    public static function sessions(): PlatformOperatorSessionService
    {
        if (self::$sessions !== null) {
            return self::$sessions;
        }

        $key = trim((string)Config::get('platform_auth.identifier_hmac_key', ''));
        if (strlen($key) < 32) {
            throw new \DomainException('PLATFORM_AUTH_CONFIGURATION_UNAVAILABLE');
        }
        $pdo = Db::connect()->connect();
        if (!$pdo instanceof PDO) {
            throw new \RuntimeException('PLATFORM_DATABASE_CONNECTION_UNAVAILABLE');
        }
        $auth = new PlatformAuthService(
            new PdoTransactionManager($pdo),
            new PdoPlatformAuthRepository($pdo),
            new PasswordHasher(),
            new SystemClock(),
            new TokenIssuer(),
            $key
        );
        $permissions = new PdoPlatformAuthorizationRepository($pdo);

        return self::$sessions = new PlatformOperatorSessionService(
            $auth,
            new PlatformAuthorizationEvaluator($permissions, new RevisionPermissionCache()),
            $permissions,
            new PlatformOperatorAccountBoundary($pdo)
        );
    }

    public static function identities(): CorePlatformOperatorIdentityPort
    {
        return new CorePlatformOperatorIdentityPort(self::sessions());
    }

    private function __construct()
    {
    }
}
