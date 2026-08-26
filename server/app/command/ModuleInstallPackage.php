<?php
declare(strict_types=1);

namespace app\command;

use app\common\service\instance\InstanceToolAccessGuard;
use app\platform\service\plugin\PluginLifecycleException;
use app\platform\service\plugin\PluginPackageException;
use app\platform\service\plugin\PluginPackageInstaller;
use PDO;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Config;
use think\facade\Db;

final class ModuleInstallPackage extends Command
{
    protected function configure()
    {
        $this->setName('module:install-package')->setDescription('Verify and install one self-contained Module package')
            ->addArgument('package', Argument::REQUIRED, 'Tar package path')
            ->addOption('sha256', null, Option::VALUE_REQUIRED, 'Expected archive SHA-256', '')
            ->addOption('signature-key-id', null, Option::VALUE_REQUIRED, 'Required trusted signature key id', '');
    }

    protected function execute(Input $input, Output $output)
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
            $result = (new PluginPackageInstaller($pdo, dirname(__DIR__, 2), $config, $trusted))->install(
                (string)$input->getArgument('package'),
                ($pin = trim((string)$input->getOption('sha256'))) === '' ? null : $pin,
                ($keyId = trim((string)$input->getOption('signature-key-id'))) === '' ? null : $keyId,
            );
            $output->writeln((string)json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            return 0;
        } catch (PluginPackageException|PluginLifecycleException $exception) {
            $output->writeln((string)json_encode(
                ['error' => $exception->errorCode],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            ));
            return 1;
        } catch (\Throwable) {
            $output->writeln('{"error":"MODULE_PACKAGE_INSTALL_FAILED"}');
            return 1;
        }
    }
}
