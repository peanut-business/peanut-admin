<?php
declare(strict_types=1);

namespace app\common\service\configuration_transfer;

use app\Modules\Official\ImportExport\Infrastructure\Configuration\ConfigurationPackageCodec;
use app\Modules\Official\ImportExport\Infrastructure\Configuration\ConfigurationTransferAdapter;
use app\Modules\Official\ImportExport\Infrastructure\Configuration\ConfigurationTransferValue;
use PDO;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\PlatformContext;

/** Transfers the application-owned tenant_setting documents. */
final readonly class TenantSettingsConfigurationAdapter implements ConfigurationTransferAdapter
{
    public function __construct(private PDO $pdo)
    {
    }

    public function key(): string
    {
        return ConfigurationPackageCodec::ADAPTER_TENANT_SETTINGS;
    }

    public function supportsCreate(): bool
    {
        return true;
    }

    public function export(TenantContext|PlatformContext $context): array
    {
        $tenantId = $this->tenantId($context);
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT namespace, config_json
FROM pa_tenant_setting
WHERE tenant_id = :tenant_id
ORDER BY namespace ASC
SQL);
        $statement->execute(['tenant_id' => $tenantId]);

        $entries = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $namespace = (string)($row['namespace'] ?? '');
            $document = $this->decodeDocument($row['config_json'] ?? null);
            $entries[] = ConfigurationTransferValue::entry($this->key(), $namespace, $document);
        }
        return $entries;
    }

    public function current(TenantContext|PlatformContext $context, string $key): array
    {
        $tenantId = $this->tenantId($context);
        $this->assertNamespace($key);
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT config_json, revision
FROM pa_tenant_setting
WHERE tenant_id = :tenant_id AND namespace = :namespace
LIMIT 1
SQL);
        $statement->execute(['tenant_id' => $tenantId, 'namespace' => $key]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return ['exists' => false, 'value' => null, 'revision' => null];
        }

        return [
            'exists' => true,
            'value' => ConfigurationTransferValue::entry($this->key(), $key, $this->decodeDocument($row['config_json'] ?? null))['value'],
            'revision' => (int)($row['revision'] ?? 0),
        ];
    }

    public function apply(
        TenantContext|PlatformContext $context,
        string $key,
        mixed $value,
        array $entry,
        ?int $revision,
    ): void {
        $tenantId = $this->tenantId($context);
        $this->assertNamespace($key);
        if (!is_array($value)) {
            throw new \RuntimeException('TRANSFER_TENANT_SETTING_INVALID');
        }
        try {
            $encoded = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (\JsonException) {
            throw new \RuntimeException('TRANSFER_TENANT_SETTING_INVALID');
        }

        $started = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $started = true;
        }
        try {
            $statement = $this->pdo->prepare(<<<'SQL'
SELECT id, revision, create_time
FROM pa_tenant_setting
WHERE tenant_id = :tenant_id AND namespace = :namespace
FOR UPDATE
SQL);
            $statement->execute(['tenant_id' => $tenantId, 'namespace' => $key]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            $now = time();
            if (!is_array($row)) {
                if ($revision !== null) {
                    throw new \RuntimeException('TRANSFER_CONFLICT');
                }
                $insert = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_tenant_setting
    (tenant_id, namespace, config_json, revision, create_time, update_time)
VALUES (:tenant_id, :namespace, :config_json, 1, :now, :now)
SQL);
                $insert->execute([
                    'tenant_id' => $tenantId,
                    'namespace' => $key,
                    'config_json' => $encoded,
                    'now' => $now,
                ]);
            } else {
                if ($revision === null || (int)$row['revision'] !== $revision) {
                    throw new \RuntimeException('TRANSFER_CONFLICT');
                }
                $update = $this->pdo->prepare(<<<'SQL'
UPDATE pa_tenant_setting
SET config_json = :config_json, revision = revision + 1, update_time = :now
WHERE id = :id AND tenant_id = :tenant_id AND namespace = :namespace AND revision = :revision
SQL);
                $update->execute([
                    'config_json' => $encoded,
                    'now' => $now,
                    'id' => (int)$row['id'],
                    'tenant_id' => $tenantId,
                    'namespace' => $key,
                    'revision' => $revision,
                ]);
                if ($update->rowCount() !== 1) {
                    throw new \RuntimeException('TRANSFER_CONFLICT');
                }
            }
            if ($started) {
                $this->pdo->commit();
            }
        } catch (\Throwable $exception) {
            if ($started && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function tenantId(TenantContext|PlatformContext $context): int
    {
        if (!$context instanceof TenantContext
            || $context->tenantId < 1
            || $context->accountId < 1
            || $context->memberId < 1
            || $context->authorizationRevision < 1
            || $context->sessionKey === ''
            || $context->clientKey === ''
            || $context->requestId === '') {
            throw new \RuntimeException('TRANSFER_TENANT_CONTEXT_INVALID');
        }
        return $context->tenantId;
    }

    /** @return array<string, mixed> */
    private function decodeDocument(mixed $encoded): array
    {
        try {
            $document = json_decode((string)$encoded, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \RuntimeException('TRANSFER_TENANT_SETTING_INVALID');
        }
        if (!is_array($document)) {
            throw new \RuntimeException('TRANSFER_TENANT_SETTING_INVALID');
        }
        return $document;
    }

    private function assertNamespace(string $namespace): void
    {
        if (preg_match('/^[a-z][a-z0-9.-]{0,63}$/D', $namespace) !== 1) {
            throw new \RuntimeException('TRANSFER_TENANT_SETTING_INVALID');
        }
    }
}
