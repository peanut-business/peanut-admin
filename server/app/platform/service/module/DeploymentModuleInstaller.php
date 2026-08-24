<?php
declare(strict_types=1);

namespace app\platform\service\module;

use PDO;
use PeanutAdmin\Kernel\Module\ManifestDocument;
use PeanutAdmin\Kernel\Module\ModuleException;

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
        return (new PdoModuleGovernanceProvider(
            $this->pdo,
            $this->serverRoot,
            $deploymentConfig
        ))->registry();
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
