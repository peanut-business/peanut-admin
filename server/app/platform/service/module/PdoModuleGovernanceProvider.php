<?php
declare(strict_types=1);

namespace app\platform\service\module;

use app\common\contract\module\ModuleGovernanceProvider;
use app\common\contract\module\ModuleExecutionGuard as ModuleExecutionGuardContract;
use app\common\contract\module\ModuleQualificationQuery;
use app\common\contract\module\PluginLifecycleCommands;
use PDO;
use app\platform\service\plugin\PluginLifecycleException;
use app\platform\service\plugin\PluginLifecycleService;
use app\platform\service\plugin\PluginLockResolver;
use app\platform\service\plugin\PluginModuleRegistryFactory;
use app\common\service\module\ModuleExecutionGuard;
use PeanutAdmin\Kernel\Module\ModuleException;
use think\facade\Config;

/** Single Host assembly point for the Module Governance contracts. */
final class PdoModuleGovernanceProvider implements ModuleGovernanceProvider
{
    private PluginModuleRegistryFactory $registryFactory;
    private ?PluginLockResolver $lockResolver = null;
    private ?DeployedTenantModuleRegistry $registryInstance = null;
    private ?ModuleQualificationQuery $qualificationInstance = null;

    /** @param array<string,mixed> $moduleConfig */
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $serverRoot,
        private readonly array $moduleConfig,
    ) {
        $this->registryFactory = new PluginModuleRegistryFactory($pdo, $serverRoot);
    }

    public static function forExecution(PDO $pdo): self
    {
        return new self($pdo, self::serverRoot(), []);
    }

    public static function forApplication(PDO $pdo): self
    {
        $config = Config::get('modules', []);
        if (!is_array($config)) {
            throw new ModuleException('MODULE_REGISTRY_UNAVAILABLE', 'Module deployment metadata is invalid.');
        }
        return new self($pdo, self::serverRoot(), $config);
    }

    public function registry(): DeployedTenantModuleRegistry
    {
        if ($this->registryInstance instanceof DeployedTenantModuleRegistry) {
            return $this->registryInstance;
        }

        $lockPath = trim((string)($this->moduleConfig['plugin_lock'] ?? ''));
        if ($lockPath !== '' && is_file($this->lockFile($lockPath))) {
            return $this->registryInstance = $this->registryFactory->fromPluginLock(
                $this->lockResolver($lockPath),
                $this->moduleConfig
            );
        }

        return $this->registryInstance = $this->registryFactory->fromDeploymentConfig($this->moduleConfig);
    }

    public function pluginLifecycle(): PluginLifecycleCommands
    {
        return new PluginLifecycleService(
            $this->pdo,
            $this->lockResolver(),
            $this->registryFactory,
            $this->moduleConfig
        );
    }

    public function qualification(): ModuleQualificationQuery
    {
        return $this->qualificationInstance ??= new ModuleQualificationQueryService(
            $this->pdo,
            $this->registry()
        );
    }

    public function executionGuard(string $moduleKey): ModuleExecutionGuardContract
    {
        return new ModuleExecutionGuard($this->pdo, $moduleKey);
    }

    private function lockResolver(?string $lockPath = null): PluginLockResolver
    {
        $lockPath ??= trim((string)($this->moduleConfig['plugin_lock'] ?? ''));
        if ($lockPath === '') {
            throw new PluginLifecycleException('PLUGIN_LOCK_INVALID', 'Plugin lock path is not configured.');
        }
        return $this->lockResolver ??= new PluginLockResolver($this->serverRoot, $lockPath);
    }

    private function lockFile(string $lockPath): string
    {
        return str_starts_with($lockPath, DIRECTORY_SEPARATOR)
            ? $lockPath
            : $this->serverRoot . '/' . ltrim($lockPath, '/');
    }

    private static function serverRoot(): string
    {
        return dirname(__DIR__, 4);
    }
}
