<?php
declare(strict_types=1);

namespace app\command;

use app\platform\service\module\ProductTenantModuleProfileService;
use PeanutAdmin\Kernel\Module\ModuleException;
use app\common\execution\ContextualCommand;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\facade\Config;

final class TenantModuleProfile extends ContextualCommand
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
                dirname(__DIR__, 2),
                $config
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
