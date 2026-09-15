<?php
declare(strict_types=1);

namespace app\Modules\Official\ImportExport;

use app\common\persistence\TenantPersistenceConfiguration;
use app\common\service\audit\AuditContractHost;
use app\common\service\authorization\AdminAuthorizationService;
use app\common\infrastructure\export\OperationLogExportProvider;
use app\Modules\Official\ImportExport\Application\ConfigurationTransferApplicationService;
use app\Modules\Official\ImportExport\Application\ImportExportApplicationService;
use app\Modules\Official\ImportExport\Application\ImportExportTaskWorkerDefinition;
use app\Modules\Official\ImportExport\Application\TaskImportExportRuntime;
use app\Modules\Official\ImportExport\Contracts\ConfigurationTransferCommands;
use app\Modules\Official\ImportExport\Contracts\ConfigurationTransferQueries;
use app\Modules\Official\ImportExport\Contracts\ImportExportCommands;
use app\Modules\Official\ImportExport\Contracts\ImportExportQueries;
use app\Modules\Official\ImportExport\Contracts\ImportExportWorkerRuntime;
use app\Modules\Official\ImportExport\Infrastructure\Authorization\AdminAsyncAuthorization;
use app\Modules\Official\ImportExport\Infrastructure\Configuration\ConfigurationPackageCodec;
use app\Modules\Official\ImportExport\Infrastructure\Configuration\CoreSettingsConfigurationAdapter;
use app\Modules\Official\ImportExport\Infrastructure\Configuration\ExternalBindingConfigurationAdapter;
use app\Modules\Official\ImportExport\Infrastructure\Configuration\TenantModuleConfigurationAdapter;
use app\Modules\Official\ImportExport\Infrastructure\Configuration\TenantSettingsConfigurationAdapter;
use app\Modules\Official\ImportExport\Infrastructure\Configuration\UnavailableSecretProtector;
use app\Modules\Official\ImportExport\Infrastructure\File\AppFileMediaGateway;
use app\Modules\Official\Task\Contracts\TaskJobRuntime;
use PeanutAdmin\ImportExport\Application\ImportExportService;
use PeanutAdmin\ImportExport\Contract\DataProviderRegistry;
use PeanutAdmin\ImportExport\Execution\CsvOperationRunner;
use PeanutAdmin\ImportExport\Execution\ImportExportTaskHandler;
use PeanutAdmin\ImportExport\Execution\ImportExportTaskSubmissionProvider;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;
use PeanutAdmin\Settings\Application\SettingAdminService;
use PeanutAdmin\Settings\Secret\SecretProtector;
use PeanutAdmin\Settings\Secret\SodiumSecretProtector;
use think\App;
use Throwable;

final class ModuleProvider implements ModuleProviderContract
{
    public function moduleKey(): string
    {
        return 'official.import-export';
    }

    public function bindings(): array
    {
        return [
            ImportExportApplicationService::class => function (App $app): ImportExportApplicationService {
                $persistence = $app->make(TenantPersistenceConfiguration::class);
                $tasks = $app->make(TaskJobRuntime::class);
                return new ImportExportApplicationService(new ImportExportService(
                    new \PeanutAdmin\ImportExport\Persistence\ImportExportStore($persistence->mode, $persistence->instanceTenantId),
                    new DataProviderRegistry([new OperationLogExportProvider()]),
                    $tasks->publisher(new ImportExportTaskSubmissionProvider()),
                    $tasks->jobs(),
                    $app->make(AuditContractHost::class),
                ));
            },
            ImportExportCommands::class => ImportExportApplicationService::class,
            ImportExportQueries::class => ImportExportApplicationService::class,
            ConfigurationTransferApplicationService::class => function (App $app): ConfigurationTransferApplicationService {
                $persistence = $app->make(TenantPersistenceConfiguration::class);
                return new ConfigurationTransferApplicationService(
                    [
                        $app->make(TenantSettingsConfigurationAdapter::class),
                        $app->make(TenantModuleConfigurationAdapter::class),
                        $app->make(ExternalBindingConfigurationAdapter::class),
                        new CoreSettingsConfigurationAdapter(
                            new SettingAdminService(
                            $this->secretProtector($app),
                                $persistence->mode,
                                $persistence->instanceTenantId,
                            ),
                            $app->make(\app\platform\service\module\ThinkPhpModuleGovernanceProvider::class),
                        ),
                    ],
                    new ConfigurationPackageCodec(),
                    $app->make(AuditContractHost::class),
                );
            },
            ConfigurationTransferCommands::class => ConfigurationTransferApplicationService::class,
            ConfigurationTransferQueries::class => ConfigurationTransferApplicationService::class,
            ImportExportTaskWorkerDefinition::class => function (App $app): ImportExportTaskWorkerDefinition {
                $persistence = $app->make(TenantPersistenceConfiguration::class);
                return new ImportExportTaskWorkerDefinition(
                    new ImportExportTaskHandler(new CsvOperationRunner(
                        new \PeanutAdmin\ImportExport\Persistence\ImportExportStore($persistence->mode, $persistence->instanceTenantId),
                        new DataProviderRegistry([new OperationLogExportProvider()]),
                        $app->make(AppFileMediaGateway::class),
                        $app->make(AuditContractHost::class),
                    )),
                    new AdminAsyncAuthorization($app->make(AdminAuthorizationService::class)),
                );
            },
            ImportExportWorkerRuntime::class => TaskImportExportRuntime::class,
        ];
    }

    private function secretProtector(App $app): SecretProtector
    {
        $encoded = trim((string)$app->config->get('peanut.settings_secrets.keys', ''));
        $activeKeyId = trim((string)$app->config->get('peanut.settings_secrets.active_key_id', ''));
        if ($encoded === '' || $activeKeyId === '') {
            return new UnavailableSecretProtector();
        }
        try {
            return SodiumSecretProtector::fromJson($encoded, $activeKeyId);
        } catch (Throwable) {
            return new UnavailableSecretProtector();
        }
    }
}
