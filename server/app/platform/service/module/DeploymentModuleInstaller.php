<?php
declare(strict_types=1);

namespace app\platform\service\module;

use PDO;
use PeanutAdmin\DataPermission\Persistence\Schema\DataPermissionSchema;
use PeanutAdmin\Kernel\Authorization\Persistence\Schema\AuthorizationSchema;
use PeanutAdmin\Kernel\Idempotency\IdempotencySchema;
use PeanutAdmin\Kernel\Migration\ModuleSchema;
use PeanutAdmin\Kernel\Module\ManifestDocument;
use PeanutAdmin\Kernel\Module\ModuleBoundaryChecker;
use PeanutAdmin\Kernel\Module\ModuleException;
use PeanutAdmin\Kernel\Module\ModuleHostLayout;
use PeanutAdmin\Kernel\Module\ModuleProvider;
use PeanutAdmin\Kernel\Module\ModuleRegistryCompiler;
use PeanutAdmin\Kernel\Persistence\Schema\KernelSchema;

/** Registers one explicitly deployed Module manifest in the deployment ledger. */
final readonly class DeploymentModuleInstaller
{
    public function __construct(
        private PDO $pdo,
        private string $serverRoot
    ) {
    }

    /**
     * @param array<string,mixed> $deploymentConfig
     * @return array{key:string,version:string,schema:int,digest:string,status:string}
     */
    public function install(string $moduleKey, array $deploymentConfig): array
    {
        $registry = $this->registry($deploymentConfig);
        $manifest = null;
        foreach ($registry->compiled()->modules as $candidate) {
            if (($candidate->data['key'] ?? null) === $moduleKey) {
                $manifest = $candidate;
                break;
            }
        }
        if (!$manifest instanceof ManifestDocument) {
            throw new ModuleException('MODULE_NOT_REGISTERED', "Unknown deployed Module: {$moduleKey}");
        }

        $identity = $this->identity($manifest);
        $now = gmdate('Y-m-d H:i:s.v');
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_module_installation (
    module_key, installed_version, manifest_schema_version, manifest_digest,
    status, revision, installed_at, activated_at, created_at, updated_at
) VALUES (
    :module_key, :installed_version, :manifest_schema_version, :manifest_digest,
    'active', 1, :installed_at, :activated_at, :created_at, :updated_at
)
ON DUPLICATE KEY UPDATE module_key = VALUES(module_key)
SQL);
            $statement->execute([
                'module_key' => $identity['key'],
                'installed_version' => $identity['version'],
                'manifest_schema_version' => $identity['schema'],
                'manifest_digest' => $identity['digest'],
                'installed_at' => $now,
                'activated_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $statement = $this->pdo->prepare(<<<'SQL'
SELECT installed_version, manifest_schema_version, manifest_digest, status
FROM pa_module_installation WHERE module_key = :module_key FOR UPDATE
SQL);
            $statement->execute(['module_key' => $identity['key']]);
            $current = $statement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($current)) {
                throw new ModuleException(
                    'MODULE_INSTALLATION_FAILED',
                    "Module installation record was not created: {$identity['key']}"
                );
            }
            $this->assertSameIdentity($identity, $current);
            $this->pdo->commit();
            return $identity;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @param array<string,mixed> $deploymentConfig */
    private function registry(array $deploymentConfig): DeployedTenantModuleRegistry
    {
        $roots = is_array($deploymentConfig['roots'] ?? null)
            ? array_values($deploymentConfig['roots'])
            : [];
        if ($roots === [] || !array_is_list($roots)) {
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
        $registry = DeployedTenantModuleRegistry::compile($this->pdo, $resolvedRoots, $compiler);
        (new ModuleBoundaryChecker($registry->compiled(), $layout, ['pa_']))->check();

        return $registry;
    }

    /** @return array{key:string,version:string,schema:int,digest:string,status:string} */
    private function identity(ManifestDocument $manifest): array
    {
        return [
            'key' => (string)$manifest->data['key'],
            'version' => (string)$manifest->data['version'],
            'schema' => (int)$manifest->data['schema_version'],
            'digest' => $manifest->digest,
            'status' => 'active',
        ];
    }

    /**
     * @param array{key:string,version:string,schema:int,digest:string,status:string} $identity
     * @param array<string,mixed> $current
     */
    private function assertSameIdentity(array $identity, array $current): void
    {
        if ((string)($current['installed_version'] ?? '') !== $identity['version']
            || (int)($current['manifest_schema_version'] ?? 0) !== $identity['schema']
            || !hash_equals($identity['digest'], (string)($current['manifest_digest'] ?? ''))
            || (string)($current['status'] ?? '') !== $identity['status']) {
            throw new ModuleException(
                'MODULE_INSTALLATION_MISMATCH',
                "Installed Module identity differs from the deployment registry: {$identity['key']}"
            );
        }
    }
}
