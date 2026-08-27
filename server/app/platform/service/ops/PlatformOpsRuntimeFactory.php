<?php
declare(strict_types=1);

namespace app\platform\service\ops;

use app\platform\service\provider\NotificationQualificationContributor;
use app\platform\service\provider\OauthQualificationContributor;
use app\platform\service\provider\PaymentQualificationContributor;
use app\platform\service\provider\PdoProviderQualificationEvidenceRepository;
use app\platform\service\provider\PlatformProviderQualificationService;
use app\platform\service\provider\StorageQualificationContributor;
use PDO;
use PeanutAdmin\OpsConsole\Maintenance\MaintenanceReasonRegistry;
use PeanutAdmin\OpsConsole\Maintenance\MaintenanceService;
use PeanutAdmin\OpsConsole\Status\OpsStatusService;
use PeanutAdmin\OpsConsole\Task\BackupRestoreProviderRegistry;
use PeanutAdmin\OpsConsole\Task\OpsTaskService;
use think\facade\Config;

final class PlatformOpsRuntimeFactory
{
    public static function status(PDO $pdo): OpsStatusService
    {
        return new OpsStatusService(
            new PlatformOpsPermissionChecker($pdo),
            self::runtimeStatusProvider($pdo)
        );
    }

    public static function runtimeStatusProvider(PDO $pdo): ApplicationRuntimeStatusProvider
    {
        return new ApplicationRuntimeStatusProvider($pdo, dirname(__DIR__, 5));
    }

    public static function maintenance(PDO $pdo): MaintenanceService
    {
        return new MaintenanceService(
            new PlatformOpsPermissionChecker($pdo),
            new MaintenanceReasonRegistry([
                'planned-upgrade',
                'database-maintenance',
                'security-maintenance',
            ]),
            new PdoMaintenanceWindowStore($pdo)
        );
    }

    public static function backupProviders(): BackupRestoreProviderRegistry
    {
        return new BackupRestoreProviderRegistry([new PairedBackupProvider()]);
    }

    public static function tasks(PDO $pdo): OpsTaskService
    {
        return new OpsTaskService(
            new PlatformOpsPermissionChecker($pdo),
            self::backupProviders(),
            new PdoOpsTaskDispatcher($pdo)
        );
    }

    public static function upgrades(PDO $pdo): PlatformUpgradeExecutionService
    {
        return new PlatformUpgradeExecutionService($pdo, dirname(__DIR__, 5));
    }

    public static function providerQualifications(PDO $pdo): PlatformProviderQualificationService
    {
        $digestKey = trim((string)Config::get('platform_auth.identifier_hmac_key', ''));
        return new PlatformProviderQualificationService(
            new PlatformOpsPermissionChecker($pdo),
            new PdoProviderQualificationEvidenceRepository($pdo),
            [
                new PaymentQualificationContributor($pdo, $digestKey),
                new NotificationQualificationContributor($pdo, $digestKey),
                new OauthQualificationContributor($pdo, $digestKey),
                new StorageQualificationContributor($pdo, $digestKey),
            ],
            $digestKey,
        );
    }

    private function __construct()
    {
    }
}
