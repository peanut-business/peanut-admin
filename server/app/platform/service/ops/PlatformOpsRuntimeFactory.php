<?php
declare(strict_types=1);

namespace app\platform\service\ops;

use app\common\service\audit\AuditContractHost;
use app\platform\service\module\PdoModuleGovernanceProvider;
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

final class PlatformOpsRuntimeFactory
{
    /** @param array<string,mixed> $moduleConfig @param array<string,string> $trustedKeys */
    public function __construct(
        private readonly PDO $pdo,
        private readonly AuditContractHost $audit,
        private readonly string $projectRoot,
        private readonly array $moduleConfig,
        private readonly array $trustedKeys,
    ) {
    }

    public function status(): OpsStatusService
    {
        return new OpsStatusService(
            new PlatformOpsPermissionChecker($this->pdo),
            $this->runtimeStatusProvider(),
        );
    }

    public function runtimeStatusProvider(): ApplicationRuntimeStatusProvider
    {
        return new ApplicationRuntimeStatusProvider(
            $this->pdo,
            $this->projectRoot,
            $this->readiness(),
            $this->moduleGovernance(),
        );
    }

    public function maintenance(): MaintenanceService
    {
        return new MaintenanceService(
            new PlatformOpsPermissionChecker($this->pdo),
            new MaintenanceReasonRegistry([
                'planned-upgrade',
                'database-maintenance',
                'security-maintenance',
                'module-lifecycle',
            ]),
            new PdoMaintenanceWindowStore($this->pdo, $this->audit),
        );
    }

    public function backupProviders(): BackupRestoreProviderRegistry
    {
        return new BackupRestoreProviderRegistry([new PairedBackupProvider()]);
    }

    public function tasks(): OpsTaskService
    {
        return new OpsTaskService(
            new PlatformOpsPermissionChecker($this->pdo),
            $this->backupProviders(),
            new PdoOpsTaskDispatcher($this->pdo, $this->audit),
        );
    }

    public function upgrades(): PlatformUpgradeExecutionService
    {
        return new PlatformUpgradeExecutionService(
            $this->pdo,
            $this->audit,
            $this->projectRoot,
            $this->runtimeStatusProvider(),
        );
    }

    public function moduleOperations(): PlatformModuleOperationExecutionService
    {
        return new PlatformModuleOperationExecutionService(
            $this->pdo,
            $this->audit,
            $this->projectRoot,
            $this->moduleConfig,
            $this->trustedKeys,
            null,
            $this->runtimeStatusProvider(),
        );
    }

    public function providerQualifications(string $digestKey): PlatformProviderQualificationService
    {
        return new PlatformProviderQualificationService(
            new PlatformOpsPermissionChecker($this->pdo),
            new PdoProviderQualificationEvidenceRepository($this->pdo),
            [
                new PaymentQualificationContributor($this->pdo, $digestKey),
                new NotificationQualificationContributor($this->pdo, $digestKey),
                new OauthQualificationContributor($this->pdo, $digestKey),
                new StorageQualificationContributor($this->pdo, $digestKey),
            ],
            $digestKey,
        );
    }

    public function moduleGovernance(): PdoModuleGovernanceProvider
    {
        return new PdoModuleGovernanceProvider(
            $this->pdo,
            $this->projectRoot . '/server',
            $this->moduleConfig,
        );
    }

    public function backups(): PlatformBackupCenterService
    {
        return new PlatformBackupCenterService(
            $this->pdo,
            $this->backupProviders(),
            $this->tasks(),
        );
    }

    public function readiness(): PlatformUpgradeReadinessService
    {
        return new PlatformUpgradeReadinessService(
            $this->pdo,
            $this->projectRoot,
            $this->moduleGovernance(),
            $this->backups(),
            $this->maintenance(),
        );
    }

    public function diagnostics(string $deploymentMode, bool $debugEnabled): PlatformDiagnosticBundleService
    {
        return new PlatformDiagnosticBundleService(
            $this->pdo,
            $this->status(),
            $this->moduleGovernance(),
            $deploymentMode,
            $debugEnabled,
        );
    }
}
