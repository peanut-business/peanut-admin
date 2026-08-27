<?php
declare(strict_types=1);

namespace app\platform\service\provider;

use PDO;

abstract class AbstractTenantBindingQualificationContributor implements ProviderQualificationContributor
{
    public function __construct(
        protected readonly PDO $pdo,
        private readonly string $digestKey,
    ) {
        if (strlen($digestKey) < 32) {
            throw new \InvalidArgumentException('PROVIDER_QUALIFICATION_DIGEST_KEY_INVALID');
        }
    }

    /**
     * @return list<array{provider_key:string,binding_provider:string,category:string,callback_required:bool}>
     */
    abstract protected function definitions(): array;

    /** @param array<string,mixed> $config */
    abstract protected function configured(string $providerKey, array $config, int $bindingStatus): bool;

    public function subjects(): array
    {
        $tenants = $this->pdo->query("SELECT id FROM pa_tenant WHERE status='active' ORDER BY id")
            ->fetchAll(PDO::FETCH_COLUMN);
        $definitions = $this->definitions();
        $bindingProviders = array_values(array_unique(array_column($definitions, 'binding_provider')));
        $bindings = $this->bindings($bindingProviders);
        $subjects = [];
        foreach ($tenants as $tenantValue) {
            $tenantId = (int)$tenantValue;
            foreach ($definitions as $definition) {
                $bindingProvider = $definition['binding_provider'];
                $row = $bindings[$tenantId][$bindingProvider] ?? null;
                $config = is_array($row) ? json_decode((string)$row['config_json'], true) : [];
                $config = is_array($config) ? $config : [];
                $status = is_array($row) ? (int)$row['status'] : 0;
                $providerKey = $definition['provider_key'];
                $subjects[] = new ProviderQualificationSubject(
                    $providerKey,
                    $definition['category'],
                    'tenant',
                    $tenantId,
                    $providerKey,
                    $this->configured($providerKey, $config, $status),
                    $definition['callback_required'],
                    null,
                    $this->digest($tenantId, $providerKey, $row),
                );
            }
        }
        return $subjects;
    }

    /** @param list<string> $providers @return array<int,array<string,array<string,mixed>>> */
    private function bindings(array $providers): array
    {
        if ($providers === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($providers), '?'));
        $statement = $this->pdo->prepare(<<<SQL
SELECT id,tenant_id,provider,identity_hash,config_json,status,update_time
FROM pa_external_channel_binding
WHERE provider IN ({$placeholders})
ORDER BY tenant_id,provider
SQL);
        $statement->execute($providers);
        $indexed = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $indexed[(int)$row['tenant_id']][(string)$row['provider']] = $row;
        }
        return $indexed;
    }

    /** @param array<string,mixed>|null $row */
    private function digest(int $tenantId, string $providerKey, ?array $row): string
    {
        $payload = is_array($row)
            ? implode("\0", [
                $providerKey,
                (string)$row['id'],
                (string)$row['identity_hash'],
                (string)$row['status'],
                (string)$row['update_time'],
                (string)$row['config_json'],
            ])
            : implode("\0", [$providerKey, (string)$tenantId, 'missing']);
        return hash_hmac('sha256', $payload, $this->digestKey);
    }

    /** @param array<string,mixed> $config @param list<string> $fields */
    protected function complete(array $config, array $fields): bool
    {
        foreach ($fields as $field) {
            if (trim((string)($config[$field] ?? '')) === '') {
                return false;
            }
        }
        return true;
    }
}
