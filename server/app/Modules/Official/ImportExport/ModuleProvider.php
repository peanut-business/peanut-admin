<?php
declare(strict_types=1);

namespace app\Modules\Official\ImportExport;

use app\Modules\Official\ImportExport\Application\ImportExportApplicationService;
use app\Modules\Official\ImportExport\Contracts\ImportExportCommands;
use app\Modules\Official\ImportExport\Contracts\ImportExportQueries;
use app\Modules\Official\ImportExport\Contracts\ConfigurationTransferCommands;
use app\Modules\Official\ImportExport\Contracts\ConfigurationTransferQueries;
use app\Modules\Official\ImportExport\Application\ConfigurationTransferApplicationService;
use app\Modules\Official\ImportExport\Application\OperationLogExportApplicationService;
use app\Modules\Official\ImportExport\Application\TenantConfigurationTransferService;
use app\Modules\Official\Task\Contracts\TaskJobRuntime;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;
use PDO;
use think\App;

final class ModuleProvider implements ModuleProviderContract
{
    public function moduleKey(): string
    {
        return 'official.import-export';
    }

    public function commands(PDO $pdo, TaskJobRuntime $tasks): ImportExportCommands
    {
        return $this->application($pdo, $tasks);
    }

    public function queries(PDO $pdo, TaskJobRuntime $tasks): ImportExportQueries
    {
        return $this->application($pdo, $tasks);
    }

    public function configurationCommands(PDO $pdo): ConfigurationTransferCommands
    {
        return new ConfigurationTransferApplicationService($pdo);
    }

    public function configurationQueries(PDO $pdo): ConfigurationTransferQueries
    {
        return new ConfigurationTransferApplicationService($pdo);
    }

    public function register(App $app): void
    {
        $app->bind(TenantConfigurationTransferService::class, fn(): TenantConfigurationTransferService => new TenantConfigurationTransferService(
            $app->make(PDO::class),
        ));
        $app->bind(OperationLogExportApplicationService::class, fn(): OperationLogExportApplicationService => new OperationLogExportApplicationService(
            $app->make(PDO::class),
        ));
    }

    private function application(PDO $pdo, TaskJobRuntime $tasks): ImportExportApplicationService
    {
        return new ImportExportApplicationService($pdo, $tasks);
    }
}
