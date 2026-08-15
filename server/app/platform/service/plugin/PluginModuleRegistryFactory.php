<?php
declare(strict_types=1);

namespace app\platform\service\plugin;

use app\platform\service\module\DeployedTenantModuleRegistry;
use app\platform\service\module\OpisManifestSchemaValidator;
use app\platform\service\module\ReflectionContractInspector;
use app\platform\service\module\StrictVersionConstraintMatcher;
use PDO;
use PeanutAdmin\DataPermission\Persistence\Schema\DataPermissionSchema;
use PeanutAdmin\Kernel\Authorization\Persistence\Schema\AuthorizationSchema;
use PeanutAdmin\Kernel\Idempotency\IdempotencySchema;
use PeanutAdmin\Kernel\Migration\ModuleSchema;
use PeanutAdmin\Kernel\Module\ModuleBoundaryChecker;
use PeanutAdmin\Kernel\Module\ModuleException;
use PeanutAdmin\Kernel\Module\ModuleHostLayout;
use PeanutAdmin\Kernel\Module\ModuleProvider;
use PeanutAdmin\Kernel\Module\ModuleRegistryCompiler;
use PeanutAdmin\Kernel\Persistence\Schema\KernelSchema;

/** The single Host construction path for deployed Module registry compilation. */
final readonly class PluginModuleRegistryFactory
{
    public function __construct(
        private PDO $pdo,
        private string $serverRoot
    ) {
    }

    /** @param array<string,mixed> $deploymentConfig */
    public function fromDeploymentConfig(array $deploymentConfig): DeployedTenantModuleRegistry
    {
        $roots = is_array($deploymentConfig['roots'] ?? null)
            ? array_values($deploymentConfig['roots'])
            : [];
        return $this->compile($this->resolveRoots($roots, true), $deploymentConfig);
    }

    /** @param array<string,mixed> $deploymentConfig */
    public function fromPluginLock(PluginLockResolver $resolver, array $deploymentConfig): DeployedTenantModuleRegistry
    {
        $legacyRoots = is_array($deploymentConfig['roots'] ?? null)
            ? array_values($deploymentConfig['roots'])
            : [];
        $roots = [
            ...$this->resolveRoots($legacyRoots, false),
            ...$resolver->moduleRoots(),
        ];
        return $this->compile(array_values(array_unique($roots)), $deploymentConfig);
    }

    /**
     * @param list<mixed> $roots
     * @return list<string>
     */
    private function resolveRoots(array $roots, bool $required): array
    {
        if (!array_is_list($roots) || ($required && $roots === [])) {
            throw new ModuleException('MODULE_REGISTRY_UNAVAILABLE', 'No deployed Module roots are configured.');
        }
        $resolvedRoots = [];
        foreach ($roots as $root) {
            if (!is_string($root) || trim($root) === '') {
                throw new ModuleException('MODULE_REGISTRY_UNAVAILABLE', 'A deployed Module root is invalid.');
            }
            $candidate = str_starts_with($root, DIRECTORY_SEPARATOR)
                ? $root
                : $this->serverRoot . '/' . ltrim($root, '/');
            $resolved = realpath($candidate);
            if ($resolved === false || !is_dir($resolved)) {
                throw new ModuleException('MODULE_REGISTRY_UNAVAILABLE', 'A deployed Module root is unavailable.');
            }
            $resolvedRoots[] = $resolved;
        }
        return $resolvedRoots;
    }

    /** @param list<string> $roots @param array<string,mixed> $deploymentConfig */
    private function compile(array $roots, array $deploymentConfig): DeployedTenantModuleRegistry
    {
        $kernelVersion = trim((string)($deploymentConfig['kernel_version'] ?? ''));
        $frontend = is_array($deploymentConfig['frontend_components'] ?? null)
            ? array_values($deploymentConfig['frontend_components'])
            : [];
        $clients = is_array($deploymentConfig['registered_client_keys'] ?? null)
            ? array_values($deploymentConfig['registered_client_keys'])
            : [];
        if ($kernelVersion === '' || $clients === [] || !array_is_list($frontend) || !array_is_list($clients)) {
            throw new ModuleException('MODULE_REGISTRY_UNAVAILABLE', 'Module deployment metadata is invalid.');
        }
        $kernelRoot = dirname((new \ReflectionClass(ModuleProvider::class))->getFileName(), 3);
        $layout = new ModuleHostLayout('server/app/Modules', 'app\\Modules', 'web/src/modules');
        $compiler = new ModuleRegistryCompiler(
            new OpisManifestSchemaValidator($kernelRoot . '/resources/schemas/module-manifest.schema.json'),
            new StrictVersionConstraintMatcher(),
            new ReflectionContractInspector(),
            $kernelVersion,
            $frontend,
            $layout,
            [
                ...KernelSchema::tableNames(),
                ...AuthorizationSchema::tableNames(),
                ...ModuleSchema::tableNames(),
                ...IdempotencySchema::tableNames(),
                ...DataPermissionSchema::tableNames(),
            ],
            $clients
        );
        $registry = DeployedTenantModuleRegistry::compile($this->pdo, $roots, $compiler);
        (new ModuleBoundaryChecker($registry->compiled(), $layout, ['pa_']))->check();
        return $registry;
    }
}
