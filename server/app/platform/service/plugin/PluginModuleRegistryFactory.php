<?php
declare(strict_types=1);

namespace app\platform\service\plugin;

use app\platform\service\module\DeployedTenantModuleRegistry;
use PDO;

/** The single Host construction path for deployed Module registry compilation. */
final readonly class PluginModuleRegistryFactory
{
    private ModuleDefinitionRegistryFactory $definitions;

    public function __construct(
        private PDO $pdo,
        private string $serverRoot
    ) {
        $this->definitions = new ModuleDefinitionRegistryFactory($serverRoot);
    }

    /** @param array<string,mixed> $deploymentConfig */
    public function fromDeploymentConfig(array $deploymentConfig): DeployedTenantModuleRegistry
    {
        return new DeployedTenantModuleRegistry(
            $this->pdo,
            $this->definitions->fromDeploymentConfig($deploymentConfig),
        );
    }

    /** @param array<string,mixed> $deploymentConfig */
    public function fromPluginLock(PluginLockResolver $resolver, array $deploymentConfig): DeployedTenantModuleRegistry
    {
        return new DeployedTenantModuleRegistry(
            $this->pdo,
            $this->definitions->fromPluginLock($resolver, $deploymentConfig),
        );
    }
}
