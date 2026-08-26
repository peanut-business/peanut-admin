<?php
declare(strict_types=1);

namespace app\platform\service\plugin;

use PDO;

/** Applies module.json catalog contributions through the same compiled Plugin registry used at runtime. */
final readonly class PluginCatalogSyncService
{
    /** @param array<string,mixed> $moduleConfig */
    public function __construct(
        private PDO $pdo,
        private string $serverRoot,
        private array $moduleConfig,
    ) {
    }

    /** @return array<string,mixed> */
    public function sync(?string $moduleKey = null): array
    {
        $resolver = new PluginLockResolver($this->serverRoot, (string)($this->moduleConfig['plugin_lock'] ?? '../plugins.lock'));
        $compiled = (new PluginModuleRegistryFactory($this->pdo, $this->serverRoot))
            ->fromPluginLock($resolver, $this->moduleConfig)
            ->compiled();
        $registered = [];
        foreach ($compiled->modules as $manifest) {
            $key = (string)($manifest->data['key'] ?? '');
            $registered[$key] = true;
        }
        if ($moduleKey !== null && !isset($registered[$moduleKey])) {
            throw new PluginLifecycleException('MODULE_NOT_REGISTERED', 'Module is not active in the deployed registry.');
        }
        return (new ModuleCatalogApplier($this->pdo))->apply(
            $compiled,
            $moduleKey === null ? null : [$moduleKey],
        );
    }

    public function catalogRevision(): string
    {
        return (new ModuleCatalogApplier($this->pdo))->catalogRevision();
    }

    /** @param list<string> $moduleKeys */
    public function invalidateTenantAuthorization(array $moduleKeys): void
    {
        (new ModuleCatalogApplier($this->pdo))->invalidateTenantAuthorization($moduleKeys);
    }
}
