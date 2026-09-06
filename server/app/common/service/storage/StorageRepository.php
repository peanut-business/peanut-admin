<?php
declare(strict_types=1);
namespace app\common\service\storage;

use app\common\tenancy\DataScopePolicy;
use PDO;
use PeanutAdmin\Kernel\Tenancy\DefaultTenantContextResolver;

/** Persists storage objects against either a physical Tenant column or the verified Standalone owner prefix. */
final readonly class StorageRepository
{
    public function __construct(
        private PDO $pdo,
        private DataScopePolicy $dataScopePolicy,
        private DefaultTenantContextResolver $defaultTenant,
    ) {}

    public function pdo(): PDO { return $this->pdo; }

    public function route(string $purpose, string $access): array
    {
        $access = StorageAccess::assertType($access);
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT a.id account_id,a.account_key,a.driver,a.name account_name,a.credential_ciphertext,a.credential_key_version,a.status account_status,
       s.id space_id,s.space_key,s.name space_name,s.access_type,s.bucket,s.region,s.endpoint,s.access_domain,s.local_path,s.status space_status
FROM pa_storage_route r JOIN pa_storage_space s ON s.id=r.space_id JOIN pa_storage_account a ON a.id=s.account_id
WHERE r.route_key IN (:purpose,:default_route) AND r.access_type=:route_access AND s.access_type=:space_access
  AND s.status='active' AND a.status='active'
ORDER BY CASE WHEN r.route_key=:purpose_order THEN 0 ELSE 1 END LIMIT 1
SQL);
        $statement->execute(['purpose'=>$purpose,'default_route'=>'default.'.$access,'route_access'=>$access,'space_access'=>$access,'purpose_order'=>$purpose]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) throw new \RuntimeException('文件用途没有可用的存储路由');
        return $this->decode($row);
    }

    /** Returns only the requested logical owner's object and preserves the ready-state filter. */
    public function objectForTenant(int $tenantId, string $fileKey, bool $readyOnly = true): ?array
    {
        [$tenantId, $ownerSql, $parameters] = $this->ownerScope($tenantId, 'f');
        $sql = $this->objectSelect($tenantId) . " WHERE {$ownerSql} AND f.file_key=:file_key"
            . ($readyOnly ? " AND f.status='ready'" : '') . ' LIMIT 1';
        $statement = $this->pdo->prepare($sql);
        $statement->execute([...$parameters, 'file_key' => $fileKey]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->decode($row) : null;
    }

    /** Requires the logical owner, object readiness and an active Tenant for delivery. */
    public function deliverableObjectForTenant(int $tenantId, string $fileKey): ?array
    {
        [$tenantId, $ownerSql, $parameters] = $this->ownerScope($tenantId, 'f');
        $statement = $this->pdo->prepare(
            $this->objectSelect($tenantId)
            . " WHERE {$ownerSql} AND f.file_key=:file_key AND f.status='ready' AND t.status='active' LIMIT 1"
        );
        $statement->execute([...$parameters, 'file_key' => $fileKey]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->decode($row) : null;
    }

    /** Resolves a public object only inside its physical or verified Standalone owner boundary. */
    public function publicObject(string $reference): ?array
    {
        $reference = trim($reference);
        $field = 'f.file_key';
        $standaloneTenantId = $this->dataScopePolicy->usesTenantColumn()
            ? null
            : $this->standaloneTenantId();
        if (preg_match('/^file_[0-9a-f]{32}$/D', $reference) !== 1) {
            $reference = ltrim($reference, '/');
            if (str_starts_with($reference, 'storage/')) $reference = substr($reference, 8);
            if (preg_match('#^tenants/v1/[1-9][0-9]*/#D', $reference) !== 1) return null;
            $reference = StoragePath::assertObjectKey($reference);
            $field = 'f.object_key';
        }
        if ($standaloneTenantId === null) {
            $ownerSql = '1=1';
            $parameters = [];
        } else {
            [$standaloneTenantId, $ownerSql, $parameters] = $this->ownerScope($standaloneTenantId, 'f');
            if ($field === 'f.object_key' && !str_starts_with($reference, $this->ownerPrefix($standaloneTenantId))) {
                return null;
            }
        }
        $statement = $this->pdo->prepare(
            $this->objectSelect($standaloneTenantId)
            . " WHERE {$ownerSql} AND {$field}=:reference AND f.access_type='public'"
            . " AND f.status='ready' AND t.status='active' LIMIT 1"
        );
        $statement->execute([...$parameters, 'reference' => $reference]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->decode($row) : null;
    }

    /** Reserves an object only when its immutable object key belongs to the resolved logical Tenant. */
    public function reserveObject(array $data): void
    {
        $tenantId = $this->logicalTenantId((int)($data['tenant_id'] ?? 0));
        if (!str_starts_with((string)($data['object_key'] ?? ''), $this->ownerPrefix($tenantId))) {
            throw new \DomainException('STORAGE_OBJECT_OWNER_MISMATCH');
        }
        if ($this->dataScopePolicy->usesTenantColumn()) {
            $sql = <<<'SQL'
INSERT INTO pa_file_object (file_key,tenant_id,purpose,access_type,storage_space_id,object_key,disposition,original_name,media_type,size_bytes,sha256,status,created_by_member_id,revision,created_at,updated_at,archived_at)
VALUES (:file_key,:tenant_id,:purpose,:access_type,:storage_space_id,:object_key,:disposition,:original_name,:media_type,:size_bytes,:sha256,'pending_write',:created_by_member_id,1,UTC_TIMESTAMP(3),UTC_TIMESTAMP(3),NULL)
SQL;
        } else {
            unset($data['tenant_id']);
            $sql = <<<'SQL'
INSERT INTO pa_file_object (file_key,purpose,access_type,storage_space_id,object_key,disposition,original_name,media_type,size_bytes,sha256,status,created_by_member_id,revision,created_at,updated_at,archived_at)
VALUES (:file_key,:purpose,:access_type,:storage_space_id,:object_key,:disposition,:original_name,:media_type,:size_bytes,:sha256,'pending_write',:created_by_member_id,1,UTC_TIMESTAMP(3),UTC_TIMESTAMP(3),NULL)
SQL;
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($data);
    }

    /** Marks ready only within the currently verified logical owner boundary. */
    public function markObjectReady(int $tenantId, string $fileKey): bool
    {
        return $this->changeStatus($tenantId, $fileKey, 'pending_write', 'ready');
    }

    /** Records a failed write only within the currently verified logical owner boundary. */
    public function markObjectWriteFailed(int $tenantId, string $fileKey): bool
    {
        return $this->changeStatus($tenantId, $fileKey, 'pending_write', 'write_failed');
    }

    /** Archives only a ready object owned by the verified logical Tenant. */
    public function archive(int $tenantId, string $fileKey): bool
    {
        [, $ownerSql, $parameters] = $this->ownerScope($tenantId);
        $statement = $this->pdo->prepare(
            "UPDATE pa_file_object SET status='archived',archived_at=UTC_TIMESTAMP(3),updated_at=UTC_TIMESTAMP(3),revision=revision+1"
            . " WHERE {$ownerSql} AND file_key=:file_key AND status='ready'"
        );
        $statement->execute([...$parameters, 'file_key' => $fileKey]);
        return $statement->rowCount() === 1;
    }

    /** Restores only an archived object owned by the verified logical Tenant. */
    public function restore(int $tenantId, string $fileKey): void
    {
        [, $ownerSql, $parameters] = $this->ownerScope($tenantId);
        $statement = $this->pdo->prepare(
            "UPDATE pa_file_object SET status='ready',archived_at=NULL,updated_at=UTC_TIMESTAMP(3),revision=revision+1"
            . " WHERE {$ownerSql} AND file_key=:file_key AND status='archived'"
        );
        $statement->execute([...$parameters, 'file_key' => $fileKey]);
    }

    public function accounts(): array
    {
        $rows = $this->pdo->query("SELECT id,account_key,driver,name,CASE WHEN credential_ciphertext IS NULL THEN NULL ELSE '********' END credential_masked,credential_key_version,credential_rotated_at,status,created_at,updated_at FROM pa_storage_account ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn(array $row): array => $this->decode($row), $rows);
    }

    public function spaces(): array
    {
        return $this->pdo->query('SELECT s.*,a.account_key,a.driver FROM pa_storage_space s JOIN pa_storage_account a ON a.id=s.account_id ORDER BY s.id')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function routes(): array
    {
        return $this->pdo->query('SELECT r.*,s.space_key,s.name space_name,a.driver FROM pa_storage_route r JOIN pa_storage_space s ON s.id=r.space_id JOIN pa_storage_account a ON a.id=s.account_id ORDER BY r.route_key')->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Changes state only after resolving the Edition owner and matching its immutable object-key prefix. */
    private function changeStatus(int $tenantId, string $fileKey, string $from, string $to): bool
    {
        [, $ownerSql, $parameters] = $this->ownerScope($tenantId);
        $statement = $this->pdo->prepare(
            "UPDATE pa_file_object SET status=:target_status,updated_at=UTC_TIMESTAMP(3),revision=revision+1"
            . " WHERE {$ownerSql} AND file_key=:file_key AND status=:source_status"
        );
        $statement->execute([
            ...$parameters,
            'file_key' => $fileKey,
            'source_status' => $from,
            'target_status' => $to,
        ]);
        return $statement->rowCount() === 1;
    }

    /** Returns the Edition-specific SQL owner predicate after validating the requested logical Tenant. */
    private function ownerScope(int $tenantId, string $alias = ''): array
    {
        $tenantId = $this->logicalTenantId($tenantId);
        $prefix = $alias === '' ? '' : $alias . '.';
        return $this->dataScopePolicy->usesTenantColumn()
            ? [$tenantId, $prefix . 'tenant_id=:tenant_id', ['tenant_id' => $tenantId]]
            : [$tenantId, $prefix . 'object_key LIKE :tenant_object_prefix', [
                'tenant_object_prefix' => $this->ownerPrefix($tenantId) . '%',
            ]];
    }

    /** Multi-tenant keeps the supplied owner; Standalone requires the unique active default Tenant. */
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

    /** Resolves the unique active default Tenant on every call so ownership is never reused from stale state. */
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

    /** Projects the verified Standalone owner while retaining the original Multi-tenant join. */
    private function objectSelect(?int $standaloneTenantId): string
    {
        $fields = 'a.id account_id,a.account_key,a.driver,a.name account_name,a.credential_ciphertext,a.credential_key_version,a.status account_status,s.space_key,s.name space_name,s.bucket,s.region,s.endpoint,s.access_domain,s.local_path,s.status space_status';
        if ($this->dataScopePolicy->usesTenantColumn()) {
            return "SELECT f.*,{$fields} FROM pa_file_object f JOIN pa_tenant t ON t.id=f.tenant_id JOIN pa_storage_space s ON s.id=f.storage_space_id JOIN pa_storage_account a ON a.id=s.account_id";
        }
        if (!is_int($standaloneTenantId) || $standaloneTenantId < 1) {
            throw new \LogicException('STORAGE_STANDALONE_TENANT_UNAVAILABLE');
        }
        return "SELECT f.*,{$standaloneTenantId} tenant_id,{$fields} FROM pa_file_object f JOIN pa_tenant t ON t.id={$standaloneTenantId} AND t.code='default' JOIN pa_storage_space s ON s.id=f.storage_space_id JOIN pa_storage_account a ON a.id=s.account_id";
    }

    private function decode(array $row): array
    {
        return $row;
    }
}
