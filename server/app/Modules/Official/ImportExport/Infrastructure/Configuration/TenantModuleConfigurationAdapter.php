<?php
declare(strict_types=1);

namespace app\Modules\Official\ImportExport\Infrastructure\Configuration;

use app\platform\service\module\OpisTenantModuleConfigValidator;
use app\platform\service\module\PdoModuleGovernanceProvider;
use PDO;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\PlatformContext;
use PeanutAdmin\Kernel\Module\TenantModuleConfigurationService;

/** Transfers only the configuration of currently effective Tenant Modules. */
final readonly class TenantModuleConfigurationAdapter implements ConfigurationTransferAdapter
{
    public function __construct(private PDO $pdo)
    {
    }

    public function key(): string
    {
        return ConfigurationPackageCodec::ADAPTER_TENANT_MODULES;
    }

    public function supportsCreate(): bool
    {
        return false;
    }

    public function export(TenantContext|PlatformContext $context): array
    {
        $tenantId = $this->tenantId($context);
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT module_key, config_json
FROM pa_tenant_module
WHERE tenant_id = :tenant_id
  AND status = 'enabled'
  AND (effective_at IS NULL OR effective_at <= CURRENT_TIMESTAMP(3))
  AND (expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP(3))
ORDER BY module_key ASC
SQL);
        $statement->execute(['tenant_id' => $tenantId]);

        $entries = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $moduleKey = (string)($row['module_key'] ?? '');
            $this->assertModuleKey($moduleKey);
            $entries[] = ConfigurationTransferValue::entry(
                $this->key(),
                $moduleKey,
                $this->decodeConfig($row['config_json'] ?? null),
            );
        }
        return $entries;
    }

    public function current(TenantContext|PlatformContext $context, string $key): array
    {
        $tenantId = $this->tenantId($context);
        $this->assertModuleKey($key);
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT status, config_json, config_revision, effective_at, expires_at
FROM pa_tenant_module
WHERE tenant_id = :tenant_id AND module_key = :module_key
LIMIT 1
SQL);
        $statement->execute(['tenant_id' => $tenantId, 'module_key' => $key]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return ['exists' => false, 'value' => null, 'revision' => null];
        }
        $effective = $this->effective($row);
        return [
            'exists' => $effective,
            'value' => ConfigurationTransferValue::entry(
                $this->key(),
                $key,
                $this->decodeConfig($row['config_json'] ?? null),
            )['value'],
            'revision' => (int)($row['config_revision'] ?? 0),
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
        $this->assertModuleKey($key);
        if (!is_array($value) || $revision === null) {
            throw new \RuntimeException('TRANSFER_TENANT_MODULE_NOT_ENABLED');
        }

        $current = $this->current($tenant, $key);
        if (!$current['exists'] || !is_int($current['revision'])) {
            throw new \RuntimeException('TRANSFER_TENANT_MODULE_NOT_ENABLED');
        }

        $registry = PdoModuleGovernanceProvider::forApplication($this->pdo)->registry();
        (new TenantModuleConfigurationService(
            $this->pdo,
            $registry->compiled(),
            new OpisTenantModuleConfigValidator(),
        ))->update(
            $tenant->tenantId,
            $key,
            $value,
            $revision,
            $tenant->memberId,
            $tenant->accountId,
            $tenant->requestId,
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

    private function effective(array $row): bool
    {
        if ((string)($row['status'] ?? '') !== 'enabled') {
            return false;
        }
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        foreach (['effective_at' => true, 'expires_at' => false] as $column => $lowerBound) {
            $value = $row[$column] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            try {
                $date = new \DateTimeImmutable((string)$value, new \DateTimeZone('UTC'));
            } catch (\Throwable) {
                return false;
            }
            if (($lowerBound && $date > $now) || (!$lowerBound && $date <= $now)) {
                return false;
            }
        }
        return true;
    }

    /** @return array<string, mixed> */
    private function decodeConfig(mixed $encoded): array
    {
        if ($encoded === null || $encoded === '') {
            return [];
        }
        try {
            $config = json_decode((string)$encoded, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \RuntimeException('TRANSFER_TENANT_MODULE_INVALID');
        }
        if (!is_array($config)) {
            throw new \RuntimeException('TRANSFER_TENANT_MODULE_INVALID');
        }
        return $config;
    }

    private function assertModuleKey(string $moduleKey): void
    {
        if (preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*(?:\.[a-z][a-z0-9]*(?:-[a-z0-9]+)*)*$/D', $moduleKey) !== 1) {
            throw new \RuntimeException('TRANSFER_TENANT_MODULE_INVALID');
        }
    }
}
