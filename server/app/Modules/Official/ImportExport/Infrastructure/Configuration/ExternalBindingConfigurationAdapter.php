<?php
declare(strict_types=1);

namespace app\Modules\Official\ImportExport\Infrastructure\Configuration;
use app\common\service\external\ExternalTenantResolutionException;
use PDO;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\PlatformContext;

/** Transfers Tenant-owned external provider bindings without callback secrets. */
final readonly class ExternalBindingConfigurationAdapter implements ConfigurationTransferAdapter
{
    private const TABLE_BINDING = 'p' . 'a_external_channel_binding';
    private const TABLE_TENANT = 'p' . 'a_tenant';

    public function __construct(private PDO $pdo)
    {
    }

    public function key(): string
    {
        return ConfigurationPackageCodec::ADAPTER_EXTERNAL_BINDINGS;
    }

    public function supportsCreate(): bool
    {
        return true;
    }

    public function export(TenantContext|PlatformContext $context): array
    {
        $tenantId = $this->tenantId($context);
        $table = self::TABLE_BINDING;
        $statement = $this->pdo->prepare(<<<SQL
SELECT provider, identity_hash, identity_hint, config_json, status
FROM {$table}
WHERE tenant_id = :tenant_id
ORDER BY provider ASC
SQL);
        $statement->execute(['tenant_id' => $tenantId]);

        $entries = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $provider = (string)($row['provider'] ?? '');
            $this->assertProvider($provider);
            $config = $this->decodeConfig($row['config_json'] ?? null);
            $identityHash = $this->identityHash($row['identity_hash'] ?? null, (int)($row['status'] ?? 0) === 1);
            $value = [
                'identity_hash' => $identityHash,
                'identity_hint' => (string)($row['identity_hint'] ?? ''),
                'config' => $config,
                'status' => (int)($row['status'] ?? 0) === 1,
            ];
            $entries[] = ConfigurationTransferValue::entry($this->key(), $provider, $value);
        }

        return $entries;
    }

    public function current(TenantContext|PlatformContext $context, string $key): array
    {
        $tenantId = $this->tenantId($context);
        $this->assertProvider($key);
        $table = self::TABLE_BINDING;
        $statement = $this->pdo->prepare(<<<SQL
SELECT identity_hash, identity_hint, config_json, status, update_time
FROM {$table}
WHERE tenant_id = :tenant_id AND provider = :provider
LIMIT 1
SQL);
        $statement->execute(['tenant_id' => $tenantId, 'provider' => $key]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return ['exists' => false, 'value' => null, 'revision' => null];
        }

        $value = [
            'identity_hash' => $this->identityHash($row['identity_hash'] ?? null, (int)($row['status'] ?? 0) === 1),
            'identity_hint' => (string)($row['identity_hint'] ?? ''),
            'config' => $this->decodeConfig($row['config_json'] ?? null),
            'status' => (int)($row['status'] ?? 0) === 1,
        ];

        return [
            'exists' => true,
            'value' => ConfigurationTransferValue::entry($this->key(), $key, $value)['value'],
            // The table predates a numeric revision column. Bind concurrency
            // to the complete persisted state so same-second writes cannot
            // evade the import plan's optimistic check.
            'revision' => $this->configurationRevision($row),
        ];
    }

    public function apply(
        TenantContext|PlatformContext $context,
        string $key,
        mixed $value,
        array $entry,
        ?int $revision,
    ): void {
        $tenant = $this->tenantContext($context);
        $this->assertProvider($key);
        if (!is_array($value) || array_is_list($value)) {
            throw new \RuntimeException('TRANSFER_EXTERNAL_BINDING_INVALID');
        }
        $expected = ['config', 'identity_hash', 'identity_hint', 'status'];
        $actual = array_keys($value);
        sort($actual, SORT_STRING);
        sort($expected, SORT_STRING);
        if ($actual !== $expected
            || !is_array($value['config'])
            || !is_bool($value['status'])
            || !is_string($value['identity_hint'])
            || strlen($value['identity_hint']) > 32
            || ($value['identity_hash'] !== null && !is_string($value['identity_hash']))) {
            throw new \RuntimeException('TRANSFER_EXTERNAL_BINDING_INVALID');
        }
        $identityHash = $value['identity_hash'];
        if ($identityHash !== null && preg_match('/^[a-f0-9]{64}$/D', $identityHash) !== 1) {
            throw new \RuntimeException('TRANSFER_EXTERNAL_BINDING_INVALID');
        }
        if ($value['status'] && $identityHash === null) {
            throw new \RuntimeException('TRANSFER_EXTERNAL_BINDING_INVALID');
        }

        $this->importConfiguration(
            $tenant,
            $key,
            $value['config'],
            $identityHash,
            $value['identity_hint'],
            $value['status'],
            $revision,
        );
    }

    private function tenantContext(TenantContext|PlatformContext $context): TenantContext
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
        return $context;
    }

    private function tenantId(TenantContext|PlatformContext $context): int
    {
        return $this->tenantContext($context)->tenantId;
    }

    /** @return array<string, mixed> */
    private function decodeConfig(mixed $encoded): array
    {
        try {
            $config = json_decode((string)$encoded, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \RuntimeException('TRANSFER_EXTERNAL_BINDING_INVALID');
        }
        if (!is_array($config)) {
            throw new \RuntimeException('TRANSFER_EXTERNAL_BINDING_INVALID');
        }
        return $config;
    }

    private function identityHash(mixed $value, bool $required): ?string
    {
        $identityHash = trim((string)$value);
        if ($identityHash === '') {
            if ($required) {
                throw new \RuntimeException('TRANSFER_EXTERNAL_BINDING_INVALID');
            }
            return null;
        }
        if (preg_match('/^[a-f0-9]{64}$/D', $identityHash) !== 1) {
            throw new \RuntimeException('TRANSFER_EXTERNAL_BINDING_INVALID');
        }
        return $identityHash;
    }

    private function assertProvider(string $provider): void
    {
        if (preg_match('/^[a-z][a-z0-9.-]{0,63}$/D', $provider) !== 1) {
            throw new \RuntimeException('TRANSFER_EXTERNAL_BINDING_INVALID');
        }
    }

    /** @param array<string, mixed> $binding */
    private function configurationRevision(array $binding): int
    {
        $state = [];
        foreach (['identity_hash', 'identity_hint', 'config_json', 'status', 'update_time'] as $key) {
            $state[$key] = $binding[$key] ?? null;
        }
        try {
            $encoded = json_encode(
                $state,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (\JsonException) {
            throw new \RuntimeException('TRANSFER_EXTERNAL_BINDING_INVALID');
        }
        $revision = hexdec(substr(hash('sha256', $encoded), 0, 15));
        if (!is_int($revision) || $revision < 1) {
            throw new \RuntimeException('TRANSFER_EXTERNAL_BINDING_INVALID');
        }
        return $revision;
    }

    /** @param array<string, mixed> $config */
    private function importConfiguration(
        TenantContext $context,
        string $provider,
        array $config,
        ?string $identityHash,
        string $identityHint,
        bool $enabled,
        ?int $expectedRevision,
    ): void {
        $tenantId = $context->tenantId;
        $provider = trim($provider);
        $identityHint = trim($identityHint);
        if (preg_match('/^[a-z][a-z0-9.-]{0,63}$/D', $provider) !== 1
            || strlen($identityHint) > 32
            || ($identityHash !== null && preg_match('/^[a-f0-9]{64}$/D', $identityHash) !== 1)
            || ($enabled && $identityHash === null)) {
            throw new \RuntimeException('TRANSFER_EXTERNAL_BINDING_INVALID');
        }

        $this->assertActiveTenant($tenantId);
        $table = self::TABLE_BINDING;
        $statement = $this->pdo->prepare(<<<SQL
SELECT id, identity_hash, identity_hint, config_json, status, update_time
FROM {$table}
WHERE tenant_id = :tenant_id AND provider = :provider
LIMIT 1
FOR UPDATE
SQL);
        $statement->execute(['tenant_id' => $tenantId, 'provider' => $provider]);
        $binding = $statement->fetch(PDO::FETCH_ASSOC);
        $binding = is_array($binding) ? $binding : null;
        $currentRevision = $binding === null ? null : $this->configurationRevision($binding);
        if (($expectedRevision === null && $binding !== null)
            || ($expectedRevision !== null && $currentRevision !== $expectedRevision)) {
            throw new \RuntimeException('TRANSFER_CONFLICT');
        }
        $this->persistImportedLocked(
            $tenantId,
            $provider,
            $config,
            $identityHash,
            $identityHint,
            $enabled,
            $binding,
        );
    }

    private function assertActiveTenant(int $tenantId): void
    {
        $tableTenant = self::TABLE_TENANT;
        $statement = $this->pdo->prepare("SELECT status FROM {$tableTenant} WHERE id = :tenant_id FOR UPDATE");
        $statement->execute(['tenant_id' => $tenantId]);
        if ((string)$statement->fetchColumn() !== 'active') {
            throw new ExternalTenantResolutionException();
        }
    }

    /** @param array<string, mixed>|null $binding @param array<string, mixed> $config */
    private function persistImportedLocked(
        int $tenantId,
        string $provider,
        array $config,
        ?string $identityHash,
        string $identityHint,
        bool $enabled,
        ?array $binding,
    ): void {
        $encoded = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $now = time();
        $table = self::TABLE_BINDING;
        if ($binding === null) {
            $statement = $this->pdo->prepare(<<<SQL
INSERT INTO {$table}
    (tenant_id, provider, callback_key, identity_hash, identity_hint, config_json, status, create_time, update_time)
VALUES
    (:tenant_id, :provider, :callback_key, :identity_hash, :identity_hint, :config_json, :status, :create_time, :update_time)
SQL);
            $statement->execute([
                'tenant_id' => $tenantId,
                'provider' => $provider,
                'callback_key' => bin2hex(random_bytes(32)),
                'identity_hash' => $identityHash
                    ?? hash('sha256', 'unconfigured:' . $provider . ':' . $tenantId),
                'identity_hint' => $identityHint,
                'config_json' => $encoded,
                'status' => $enabled ? 1 : 0,
                'create_time' => $now,
                'update_time' => $now,
            ]);
            return;
        }

        $columns = 'config_json = :config_json, status = :status, update_time = :update_time';
        $parameters = [
            'config_json' => $encoded,
            'status' => $enabled ? 1 : 0,
            'update_time' => $now,
            'id' => (int)$binding['id'],
            'tenant_id' => $tenantId,
            'provider' => $provider,
        ];
        if ($identityHash !== null) {
            $columns .= ', identity_hash = :identity_hash, identity_hint = :identity_hint';
            $parameters['identity_hash'] = $identityHash;
            $parameters['identity_hint'] = $identityHint;
        }
        $statement = $this->pdo->prepare(<<<SQL
UPDATE {$table}
SET {$columns}
WHERE id = :id AND tenant_id = :tenant_id AND provider = :provider
SQL);
        $statement->execute($parameters);
    }
}
