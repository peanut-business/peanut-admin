<?php
declare(strict_types=1);

namespace app\command;

use app\common\service\audit\AuditContractHost;
use app\platform\service\module\ProductTenantModuleProfileService;
use app\platform\service\module\PdoModuleGovernanceProvider;
use PeanutAdmin\Kernel\Module\ModuleException;
use PeanutAdmin\Kernel\Module\Persistence\PdoModuleRuntimeRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTransactionManager;
use app\common\execution\DatabaseContextualCommand;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\facade\Config;

final class TenantModuleProfile extends DatabaseContextualCommand
{
    protected function configure()
    {
        $this->setName('tenant-module:apply-profile')
            ->setDescription('Apply an explicit application-owned Tenant Module product profile')
            ->addArgument('profile', Argument::REQUIRED, 'Product profile: standalone or demo');
    }

    protected function handle(Input $input, Output $output): int
    {
        try {
            $pdo = $this->database();
            $config = Config::get('modules', []);
            if (!is_array($config)) {
                throw new \RuntimeException('MODULE_REGISTRY_UNAVAILABLE');
            }
            $result = (new ProductTenantModuleProfileService(
                $pdo,
                new PdoTransactionManager($pdo),
                new PdoModuleRuntimeRepository($pdo, true),
                new PdoModuleGovernanceProvider($pdo, dirname(__DIR__, 2), $config),
                AuditContractHost::fromPdo($pdo),
            ))->apply(trim((string)$input->getArgument('profile')));
            $output->writeln((string)json_encode(
                $result,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
            ));
            return 0;
        } catch (ModuleException $exception) {
            $output->writeln((string)json_encode(
                ['error' => $exception->errorCode],
                JSON_THROW_ON_ERROR
            ));
            return 1;
        } catch (\Throwable $exception) {
            $message = $exception->getMessage();
            $output->writeln((string)json_encode(
                ['error' => preg_match('/^[A-Z0-9_]+$/D', $message) === 1
                    ? $message
                    : 'TENANT_MODULE_PROFILE_FAILED'],
                JSON_THROW_ON_ERROR
            ));
            return 1;
        }
    }
}
