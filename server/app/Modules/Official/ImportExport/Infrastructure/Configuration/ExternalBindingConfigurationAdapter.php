<?php
declare(strict_types=1);

namespace app\Modules\Official\ImportExport\Infrastructure\Configuration;

use app\common\service\external\ExternalChannelBindingService;
use PDO;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\PlatformContext;

/** Transfers Tenant-owned external provider bindings without callback secrets. */
final readonly class ExternalBindingConfigurationAdapter implements ConfigurationTransferAdapter
{
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
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT provider, identity_hash, identity_hint, config_json, status
FROM pa_external_channel_binding
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
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT identity_hash, identity_hint, config_json, status, update_time
FROM pa_external_channel_binding
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
            'revision' => ExternalChannelBindingService::configurationRevision($row),
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

        // ExternalChannelBindingService owns callback-key generation and row
        // mutation. The imported package can never choose that routing key.
        ExternalChannelBindingService::importConfiguration(
            $this->pdo,
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
}
