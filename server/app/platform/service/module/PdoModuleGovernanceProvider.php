<?php
declare(strict_types=1);

namespace app\platform\service\module;

use app\common\contract\module\ModuleGovernanceProvider;
use app\common\contract\module\ModuleQualificationQuery;
use app\common\contract\module\PluginLifecycleCommands;
use PDO;
use app\platform\service\plugin\PluginLifecycleException;
use app\platform\service\plugin\PluginLifecycleService;
use app\platform\service\plugin\PluginLockResolver;
use app\platform\service\plugin\PluginModuleRegistryFactory;

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

}
