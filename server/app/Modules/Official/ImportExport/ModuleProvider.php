<?php
declare(strict_types=1);

namespace app\Modules\Official\ImportExport;

use app\common\composition\ModuleBindingContributor;
use app\Modules\Official\ImportExport\Application\ImportExportApplicationService;
use app\Modules\Official\ImportExport\Contracts\ImportExportCommands;
use app\Modules\Official\ImportExport\Contracts\ImportExportQueries;
use app\Modules\Official\ImportExport\Contracts\ConfigurationTransferCommands;
use app\Modules\Official\ImportExport\Contracts\ConfigurationTransferQueries;
use app\Modules\Official\ImportExport\Application\ConfigurationTransferApplicationService;
use app\Modules\Official\ImportExport\Application\OperationLogExportApplicationService;
use app\Modules\Official\ImportExport\Application\TaskImportExportRuntime;
use app\Modules\Official\ImportExport\Application\TenantConfigurationTransferService;
use app\Modules\Official\ImportExport\Infrastructure\File\AppFileMediaGateway;
use app\Modules\Official\Task\Contracts\TaskJobRuntime;
use app\common\service\authorization\AdminAuthorizationService;
use app\common\service\storage\StorageService;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;
use PeanutAdmin\Kernel\Persistence\TransactionManager;
use PDO;
use think\App;

final class ModuleProvider implements ModuleProviderContract, ModuleBindingContributor
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

    public function configurationCommands(PDO $pdo, TransactionManager $transactions): ConfigurationTransferCommands
    {
        return new ConfigurationTransferApplicationService($pdo, $transactions);
    }

    public function configurationQueries(PDO $pdo, TransactionManager $transactions): ConfigurationTransferQueries
    {
        return new ConfigurationTransferApplicationService($pdo, $transactions);
    }

    public function bindings(): array
    {
        return [
            ImportExportApplicationService::class => fn(App $app): ImportExportApplicationService => $this->application(
                $app->make(PDO::class),
                $app->make(TaskJobRuntime::class),
            ),
            ImportExportCommands::class => fn(App $app): ImportExportCommands => $app->make(ImportExportApplicationService::class),
            ImportExportQueries::class => fn(App $app): ImportExportQueries => $app->make(ImportExportApplicationService::class),
            ConfigurationTransferApplicationService::class => fn(App $app): ConfigurationTransferApplicationService => new ConfigurationTransferApplicationService(
                $app->make(PDO::class),
                $app->make(TransactionManager::class),
            ),
            ConfigurationTransferCommands::class => fn(App $app): ConfigurationTransferCommands => $app->make(ConfigurationTransferApplicationService::class),
            ConfigurationTransferQueries::class => fn(App $app): ConfigurationTransferQueries => $app->make(ConfigurationTransferApplicationService::class),
            TenantConfigurationTransferService::class => fn(App $app): TenantConfigurationTransferService => new TenantConfigurationTransferService(
                $app->make(PDO::class),
                $app->make(TransactionManager::class),
            ),
            AppFileMediaGateway::class => fn(App $app): AppFileMediaGateway => new AppFileMediaGateway(
                $app->make(PDO::class),
                $app->make(StorageService::class),
            ),
            TaskImportExportRuntime::class => fn(App $app): TaskImportExportRuntime => new TaskImportExportRuntime(
                $app->make(PDO::class),
                $app->make(TaskJobRuntime::class),
                $app->make(AppFileMediaGateway::class),
            ),
            OperationLogExportApplicationService::class => fn(App $app): OperationLogExportApplicationService => new OperationLogExportApplicationService(
                new AdminAuthorizationService($app->make(PDO::class)),
                $app->make(TaskImportExportRuntime::class),
            ),
        ];
    }

    private function application(PDO $pdo, TaskJobRuntime $tasks): ImportExportApplicationService
    {
        return new ImportExportApplicationService($pdo, $tasks);
    }
}
