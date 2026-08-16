<?php
declare(strict_types=1);

namespace app\platform\invitation;

use app\platform\query\PlatformControlPlaneQueryService;
use app\platform\service\PlatformOperatorSessionService;
use PDO;
use think\facade\Config;
use think\facade\Db;

final class PlatformInvitationRuntimeFactory
{
    private static ?TenantOwnerInvitationAdminService $invitations = null;
    private static ?TenantOwnerInvitationPublicService $publicInvitations = null;
    private static ?PlatformControlPlaneQueryService $queries = null;

    public static function invitations(): TenantOwnerInvitationAdminService
    {
        return self::$invitations ??= new TenantOwnerInvitationAdminService(
            self::pdo(),
            self::sessions(),
            new UnavailableOwnerInvitationDeliveryPort(),
            OwnerInvitationRuntimePolicy::fromEnvironment(
                (string)env('APP_ENV', ''),
                (string)Config::get('platform_invitation.delivery_mode', 'auto')
            )
        );
    }

    public static function publicInvitations(): TenantOwnerInvitationPublicService
    {
        return self::$publicInvitations ??= new TenantOwnerInvitationPublicService(self::pdo());
    }

    public static function queries(): PlatformControlPlaneQueryService
    {
        return self::$queries ??= new PlatformControlPlaneQueryService(self::pdo(), self::sessions());
    }

    private static function sessions(): PlatformOperatorSessionService
    {
        return \app\platform\service\PlatformRuntimeFactory::sessions();
    }

    private static function pdo(): PDO
    {
        $pdo = Db::connect()->connect();
        if (!$pdo instanceof PDO) {
            throw new \RuntimeException('PLATFORM_DATABASE_CONNECTION_UNAVAILABLE');
        }
        return $pdo;
    }

    private function __construct()
    {
    }
}
