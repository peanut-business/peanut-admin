<?php
declare(strict_types=1);

namespace app\common\infrastructure\storage;

use app\common\tenancy\DataScopePolicy;
use PeanutAdmin\FileMedia\Storage\StorageObjectKey;
use PeanutAdmin\Kernel\Tenancy\DefaultTenantContextResolver;
use think\db\Query;
use think\facade\Db;

/** Persists storage objects through ThinkPHP while enforcing the Edition-specific owner boundary. */
final readonly class StorageRepository
{
    public function __construct(
        private DataScopePolicy $dataScopePolicy,
        private DefaultTenantContextResolver $defaultTenant,
    ) {}

    public function route(string $purpose, string $access): array
    {
        $access = StorageAccess::assertType($access);
        $row = $this->routeRow($purpose, $access) ?? $this->routeRow('default.' . $access, $access);
        if ($row === null) {
            throw new \RuntimeException('文件用途没有可用的存储路由');
        }
        return $row;
    }

    public function objectForTenant(int $tenantId, string $fileKey, bool $readyOnly = true): ?array
    {
        $query = $this->objectQuery($this->logicalTenantId($tenantId))->where('f.file_key', $fileKey);
        if ($readyOnly) {
            $query->where('f.status', 'ready');
        }
        return $this->find($query);
    }

    public function deliverableObjectForTenant(int $tenantId, string $fileKey): ?array
    {
        return $this->find(
            $this->objectQuery($this->logicalTenantId($tenantId))
                ->where('f.file_key', $fileKey)
                ->where('f.status', 'ready')
                ->where('t.status', 'active'),
        );
    }

    public function publicObject(string $reference): ?array
    {
        $reference = trim($reference);
        $field = 'f.file_key';
        $tenantId = $this->dataScopePolicy->usesTenantColumn() ? null : $this->standaloneTenantId();
        if (preg_match('/^file_[0-9a-f]{32}$/D', $reference) !== 1) {
            $reference = ltrim($reference, '/');
            if (str_starts_with($reference, 'storage/')) {
                $reference = substr($reference, 8);
            }
            if (preg_match('#^tenants/v1/[1-9][0-9]*/#D', $reference) !== 1) {
                return null;
            }
            $reference = StorageObjectKey::assert($reference);
            $field = 'f.object_key';
            if ($tenantId !== null && !str_starts_with($reference, $this->ownerPrefix($tenantId))) {
                return null;
            }
        }
        return $this->find(
            $this->objectQuery($tenantId)
                ->where($field, $reference)
                ->where('f.access_type', 'public')
                ->where('f.status', 'ready')
                ->where('t.status', 'active'),
        );
    }

    public function reserveObject(array $data): void
    {
        $tenantId = $this->logicalTenantId((int)($data['tenant_id'] ?? 0));
        if (!str_starts_with((string)($data['object_key'] ?? ''), $this->ownerPrefix($tenantId))) {
            throw new \DomainException('STORAGE_OBJECT_OWNER_MISMATCH');
        }
        if (!$this->dataScopePolicy->usesTenantColumn()) {
            unset($data['tenant_id']);
        }
        Db::name('file_object')->insert([
            ...$data,
            'status' => 'pending_write',
            'revision' => 1,
            'created_at' => Db::raw('UTC_TIMESTAMP(3)'),
            'updated_at' => Db::raw('UTC_TIMESTAMP(3)'),
            'archived_at' => null,
        ]);
    }

    public function markObjectReady(int $tenantId, string $fileKey): bool
    {
        return $this->changeStatus($tenantId, $fileKey, 'pending_write', 'ready');
    }

    public function markObjectWriteFailed(int $tenantId, string $fileKey): bool
    {
        return $this->changeStatus($tenantId, $fileKey, 'pending_write', 'write_failed');
    }

    public function archive(int $tenantId, string $fileKey): bool
    {
        return $this->ownedObjects($this->logicalTenantId($tenantId))
            ->where('file_key', $fileKey)->where('status', 'ready')
            ->update([
                'status' => 'archived',
                'archived_at' => Db::raw('UTC_TIMESTAMP(3)'),
                'updated_at' => Db::raw('UTC_TIMESTAMP(3)'),
                'revision' => Db::raw('revision+1'),
            ]) === 1;
    }

    public function restore(int $tenantId, string $fileKey): void
    {
        $this->ownedObjects($this->logicalTenantId($tenantId))
            ->where('file_key', $fileKey)->where('status', 'archived')
            ->update([
                'status' => 'ready',
                'archived_at' => null,
                'updated_at' => Db::raw('UTC_TIMESTAMP(3)'),
                'revision' => Db::raw('revision+1'),
            ]);
    }

    public function accounts(): array
    {
        return Db::name('storage_account')
            ->field('id,account_key,driver,name,credential_key_version,credential_rotated_at,status,created_at,updated_at')
            ->fieldRaw("CASE WHEN credential_ciphertext IS NULL THEN NULL ELSE '********' END AS credential_masked")
            ->order('id')->select()->toArray();
    }

    public function spaces(): array
    {
        return Db::name('storage_space')->alias('s')
            ->join('storage_account a', 'a.id=s.account_id')
            ->field('s.*,a.account_key,a.driver')->order('s.id')->select()->toArray();
    }

    public function routes(): array
    {
        return Db::name('storage_route')->alias('r')
            ->join('storage_space s', 's.id=r.space_id')
            ->join('storage_account a', 'a.id=s.account_id')
            ->field('r.*,s.space_key,s.name AS space_name,a.driver')
            ->order('r.route_key')->select()->toArray();
    }

    private function routeRow(string $routeKey, string $access): ?array
    {
        return $this->find(
            Db::name('storage_route')->alias('r')
                ->join('storage_space s', 's.id=r.space_id')
                ->join('storage_account a', 'a.id=s.account_id')
                ->where('r.route_key', $routeKey)->where('r.access_type', $access)
                ->where('s.access_type', $access)->where('s.status', 'active')->where('a.status', 'active')
                ->field('a.id AS account_id,a.account_key,a.driver,a.name AS account_name,a.credential_ciphertext,a.credential_key_version,a.status AS account_status')
                ->field('s.id AS space_id,s.space_key,s.name AS space_name,s.access_type,s.bucket,s.region,s.endpoint,s.access_domain,s.local_path,s.status AS space_status'),
        );
    }

    private function changeStatus(int $tenantId, string $fileKey, string $from, string $to): bool
    {
        return $this->ownedObjects($this->logicalTenantId($tenantId))
            ->where('file_key', $fileKey)->where('status', $from)
            ->update([
                'status' => $to,
                'updated_at' => Db::raw('UTC_TIMESTAMP(3)'),
                'revision' => Db::raw('revision+1'),
            ]) === 1;
    }

    private function ownedObjects(int $tenantId): Query
    {
        $query = Db::name('file_object');
        return $this->dataScopePolicy->usesTenantColumn()
            ? $query->where('tenant_id', $tenantId)
            : $query->whereLike('object_key', $this->ownerPrefix($tenantId) . '%');
    }

    private function logicalTenantId(int $tenantId): int
    {
        if ($tenantId < 1) {
            throw new \DomainException('STORAGE_TENANT_INVALID');
        }
        if ($this->dataScopePolicy->usesTenantColumn()) {
            return $tenantId;
        }
        $defaultTenantId = $this->standaloneTenantId();
        if ($tenantId !== $defaultTenantId) {
            throw new \DomainException('STORAGE_OBJECT_OWNER_MISMATCH');
        }
        return $defaultTenantId;
    }

    private function standaloneTenantId(): int
    {
        return $this->defaultTenant->system(
            'storage-repository',
            'storage.resolve-default-tenant',
            'storage-repository-default-tenant',
        )->tenantId;
    }

    private function ownerPrefix(int $tenantId): string
    {
        return 'tenants/v1/' . $tenantId . '/';
    }

    private function objectQuery(?int $standaloneTenantId): Query
    {
        $query = Db::name('file_object')->alias('f')
            ->join('storage_space s', 's.id=f.storage_space_id')
            ->join('storage_account a', 'a.id=s.account_id')
            ->field('f.*,a.id AS account_id,a.account_key,a.driver,a.name AS account_name,a.credential_ciphertext,a.credential_key_version,a.status AS account_status')
            ->field('s.space_key,s.name AS space_name,s.bucket,s.region,s.endpoint,s.access_domain,s.local_path,s.status AS space_status');
        if ($this->dataScopePolicy->usesTenantColumn()) {
            return $query->join('tenant t', 't.id=f.tenant_id')->where('f.tenant_id', $standaloneTenantId);
        }
        if (!is_int($standaloneTenantId) || $standaloneTenantId < 1) {
            throw new \LogicException('STORAGE_STANDALONE_TENANT_UNAVAILABLE');
        }
        return $query->join('tenant t', 't.id=' . $standaloneTenantId)
            ->where('t.code', 'default')
            ->whereLike('f.object_key', $this->ownerPrefix($standaloneTenantId) . '%')
            ->fieldRaw($standaloneTenantId . ' AS tenant_id');
    }

    private function find(Query $query): ?array
    {
        $row = $query->find();
        return $row === null ? null : (is_array($row) ? $row : $row->toArray());
    }
}
