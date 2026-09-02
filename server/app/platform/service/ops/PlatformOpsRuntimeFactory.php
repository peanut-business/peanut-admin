<?php
declare(strict_types=1);

namespace app\platform\service\ops;

use app\common\service\audit\AuditContractHost;
use app\platform\service\module\PdoModuleGovernanceProvider;
use app\platform\service\plugin\PluginRuntimeGovernanceService;
use DateTimeImmutable;
use app\platform\service\provider\NotificationQualificationContributor;
use app\platform\service\provider\OauthQualificationContributor;
use app\platform\service\provider\PaymentQualificationContributor;
use app\platform\service\provider\PdoProviderQualificationEvidenceRepository;
use app\platform\service\provider\PlatformProviderQualificationService;
use app\platform\service\provider\StorageQualificationContributor;
use PDO;
use PeanutAdmin\OpsConsole\Maintenance\MaintenanceReasonRegistry;
use PeanutAdmin\OpsConsole\Maintenance\MaintenanceService;
use PeanutAdmin\OpsConsole\Logs\RuntimeLogProviderRegistry;
use PeanutAdmin\OpsConsole\Logs\RuntimeLogService;
use PeanutAdmin\OpsConsole\Logs\SafeLogMessageCatalog;
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
            $this->maintenanceStore(),
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
            $this->taskDispatcher(),
        );
    }

    public function upgrades(): PlatformUpgradeExecutionService
    {
        return new PlatformUpgradeExecutionService(
            $this->pdo,
            $this->taskDispatcher(),
            $this->projectRoot,
            $this->runtimeStatusProvider(),
        );
    }

    public function moduleOperations(): PlatformModuleOperationExecutionService
    {
        return new PlatformModuleOperationExecutionService(
            $this->pdo,
            $this->taskDispatcher(),
            $this->moduleRequests(),
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
        $permissions = new PlatformOpsPermissionChecker($this->pdo);
        return new PlatformDiagnosticBundleService(
            $this->pdo,
            $permissions,
            fn(DateTimeImmutable $since): RuntimeLogService => new RuntimeLogService(
                $permissions,
                new RuntimeLogProviderRegistry([
                    new PlatformAuditRuntimeLogProvider($this->pdo, $since->format('Y-m-d H:i:s.v')),
                ]),
                new SafeLogMessageCatalog([]),
            ),
            $this->status(),
            $this->moduleGovernance(),
            $deploymentMode,
            $debugEnabled,
        );
    }

    public function upgradeTaskExecution(): PdoUpgradeTaskExecutionService
    {
        return new PdoUpgradeTaskExecutionService(
            $this->pdo,
            $this->audit,
            $this->taskDispatcher(),
            $this->maintenanceStore(),
            $this->projectRoot,
            $this->backupProviders(),
            $this->runtimeStatusProvider(),
        );
    }

    public function moduleTaskExecution(): PdoModuleOperationTaskExecutionService
    {
        return new PdoModuleOperationTaskExecutionService(
            $this->pdo,
            $this->audit,
            $this->taskDispatcher(),
            $this->maintenanceStore(),
            $this->moduleRequests(),
            $this->backupProviders(),
            $this->runtimeStatusProvider(),
        );
    }

    private function taskDispatcher(): PdoOpsTaskDispatcher
    {
        return new PdoOpsTaskDispatcher($this->pdo, $this->audit);
    }

    private function maintenanceStore(): PdoMaintenanceWindowStore
    {
        return new PdoMaintenanceWindowStore($this->pdo, $this->audit);
    }

    private function moduleRequests(): DeploymentModuleRequestService
    {
        return new DeploymentModuleRequestService(
            $this->pdo,
            $this->projectRoot,
            $this->moduleConfig,
            $this->trustedKeys,
            new PluginRuntimeGovernanceService(
                $this->pdo,
                $this->projectRoot . '/server',
                $this->moduleConfig,
            ),
        );
    }
}
