<?php
declare(strict_types=1);

namespace app\command;

use app\common\service\instance\InstanceToolAccessGuard;
use app\platform\service\plugin\PluginLifecycleException;
use app\platform\service\plugin\PluginPackageException;
use app\platform\service\plugin\PluginPackageInstaller;
use PDO;
use app\common\execution\ContextualCommand;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Config;
use think\facade\Db;

final class ModuleUpdatePackage extends ContextualCommand
{
    protected function configure()
    {
        $this->setName('module:update-package')->setDescription('Verify and explicitly update one installed Module package')
            ->addArgument('package', Argument::REQUIRED, 'Tar package path')
            ->addOption('sha256', null, Option::VALUE_REQUIRED, 'Expected archive SHA-256', '')
            ->addOption('signature-key-id', null, Option::VALUE_REQUIRED, 'Required trusted signature key id', '')
            ->addOption('dry-run', null, Option::VALUE_NONE, 'Return the verified update plan without product-state writes');
    }

    protected function handle(Input $input, Output $output): int
    {
        try {
            if (strtolower(trim((string)env('APP_ENV', ''))) !== 'development'
                || !app()->isDebug()
                || !InstanceToolAccessGuard::fromConfiguredValue(Config::get('deployment.mode'))->allows()) {
                throw new PluginPackageException('MODULE_RUNTIME_MUTATION_DISABLED', 'Runtime Module mutation is disabled.');
            }
            $pdo = Db::connect()->connect();
            if (!$pdo instanceof PDO) {
                throw new PluginPackageException('PLATFORM_DATABASE_CONNECTION_UNAVAILABLE', 'Database is unavailable.');
            }
            $trusted = [];
            foreach ((array)Config::get('module_packages.trusted_ed25519_keys', []) as $keyId => $encoded) {
                $decoded = is_string($encoded) ? base64_decode($encoded, true) : false;
                if (is_string($keyId) && is_string($decoded) && strlen($decoded) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
                    $trusted[$keyId] = $decoded;
                }
            }
            $config = Config::get('modules', []);
            if (!is_array($config)) {
                throw new PluginPackageException('MODULE_REGISTRY_UNAVAILABLE', 'Module deployment config is invalid.');
            }
            $result = (new PluginPackageInstaller($pdo, dirname(__DIR__, 2), $config, $trusted))->update(
                (string)$input->getArgument('package'),
                ($pin = trim((string)$input->getOption('sha256'))) === '' ? null : $pin,
                ($keyId = trim((string)$input->getOption('signature-key-id'))) === '' ? null : $keyId,
                (bool)$input->getOption('dry-run'),
            );
            $output->writeln((string)json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            return 0;
        } catch (PluginPackageException|PluginLifecycleException $exception) {
            $output->writeln((string)json_encode(
                [
                    'code' => $exception->errorCode,
                    'reason' => $exception->getMessage(),
                    'remediation' => $this->remediation($exception->errorCode),
                ] + ($exception instanceof PluginPackageException ? $exception->details : []),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            ));
            return 1;
        } catch (\Throwable) {
            $output->writeln('{"code":"MODULE_PACKAGE_UPDATE_FAILED","reason":"Package update failed.","remediation":"Inspect the restricted operator log and keep the current recovery state."}');
            return 1;
        }
    }

    private function remediation(string $code): string
    {
        return match ($code) {
            'PLUGIN_DOWNGRADE_REJECTED' => 'Build and sign a version higher than the installed Package.',
            'PACKAGE_VERSION_IDENTITY_CONFLICT' => 'Publish changed contents under a higher immutable version.',
            'PLUGIN_UPDATE_SCOPE_CHANGED' => 'Keep the existing Bundle member set; deliver membership changes as a separately approved lifecycle decision.',
            'PACKAGE_UPDATE_RECOVERY_REQUIRED' => 'Keep maintenance active and use the returned recovery pointer with a verified paired backup.',
            'PLUGIN_NOT_INSTALLED' => 'Install the Package before requesting an update.',
            default => 'Correct the reported preflight condition and rerun dry-run before updating.',
        };
    }
}
