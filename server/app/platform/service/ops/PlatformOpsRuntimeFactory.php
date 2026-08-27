<?php
declare(strict_types=1);

namespace app\platform\service\ops;

use PDO;
use PeanutAdmin\OpsConsole\Maintenance\MaintenanceReasonRegistry;
use PeanutAdmin\OpsConsole\Maintenance\MaintenanceService;
use PeanutAdmin\OpsConsole\Status\OpsStatusService;
use PeanutAdmin\OpsConsole\Task\BackupRestoreProviderRegistry;

final class PlatformOpsRuntimeFactory
{
    public static function status(PDO $pdo): OpsStatusService
    {
        return new OpsStatusService(
            new PlatformOpsPermissionChecker($pdo),
            new ApplicationRuntimeStatusProvider($pdo, dirname(__DIR__, 5))
        );
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
            new ReadOnlyMaintenanceWindowStore($pdo)
        );
    }

    public static function backupProviders(): BackupRestoreProviderRegistry
    {
        return new BackupRestoreProviderRegistry([new PairedBackupProvider()]);
    }

    private function __construct()
    {
    }
}
