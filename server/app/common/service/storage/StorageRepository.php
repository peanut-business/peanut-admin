<?php
declare(strict_types=1);
namespace app\common\service\storage;

use PDO;
use think\facade\Db;

final readonly class StorageRepository
{
    public function __construct(private PDO $pdo) {}

    public static function fromDefaultConnection(): self
    {
        $pdo = Db::connect()->connect();
        if (!$pdo instanceof PDO) throw new \RuntimeException('存储数据库不可用');
        return new self($pdo);
    }

    public function pdo(): PDO { return $this->pdo; }

    public function route(string $purpose, string $access): array
    {
        $access = StorageAccess::assertType($access);
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT a.id account_id,a.account_key,a.driver,a.name account_name,a.credential_ref,a.status account_status,
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

    public function objectForTenant(int $tenantId, string $fileKey, bool $readyOnly = true): ?array
    {
        $sql=$this->objectSelect().' WHERE f.tenant_id=:tenant_id AND f.file_key=:file_key'.($readyOnly?" AND f.status='ready'":'').' LIMIT 1';
        $statement=$this->pdo->prepare($sql); $statement->execute(['tenant_id'=>$tenantId,'file_key'=>$fileKey]);
        $row=$statement->fetch(PDO::FETCH_ASSOC); return is_array($row)?$this->decode($row):null;
    }

    public function publicObject(string $reference): ?array
    {
        $reference=trim($reference); $field='f.file_key';
        if (preg_match('/^file_[0-9a-f]{32}$/D',$reference)!==1) {
            $reference=ltrim($reference,'/');
            if(str_starts_with($reference,'storage/')) $reference=substr($reference,8);
            if(preg_match('#^tenants/v1/[1-9][0-9]*/#D',$reference)!==1) return null;
            $reference=StoragePath::assertObjectKey($reference); $field='f.object_key';
        }
        $statement=$this->pdo->prepare($this->objectSelect()." WHERE {$field}=:reference AND f.access_type='public' AND f.status='ready' LIMIT 1");
        $statement->execute(['reference'=>$reference]); $row=$statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row)?$this->decode($row):null;
    }

    public function reserveObject(array $data): void
    {
        $statement=$this->pdo->prepare(<<<'SQL'
INSERT INTO pa_file_object (file_key,tenant_id,purpose,access_type,storage_space_id,object_key,disposition,original_name,media_type,size_bytes,sha256,status,created_by_member_id,revision,created_at,updated_at,archived_at)
VALUES (:file_key,:tenant_id,:purpose,:access_type,:storage_space_id,:object_key,:disposition,:original_name,:media_type,:size_bytes,:sha256,'pending_write',:created_by_member_id,1,UTC_TIMESTAMP(3),UTC_TIMESTAMP(3),NULL)
SQL); $statement->execute($data);
    }

    public function markObjectReady(int $tenantId, string $fileKey): bool
    {
        $s=$this->pdo->prepare("UPDATE pa_file_object SET status='ready',updated_at=UTC_TIMESTAMP(3),revision=revision+1 WHERE tenant_id=:tenant_id AND file_key=:file_key AND status='pending_write'");
        $s->execute(['tenant_id'=>$tenantId,'file_key'=>$fileKey]); return $s->rowCount()===1;
    }

    public function markObjectWriteFailed(int $tenantId, string $fileKey): void
    {
        $s=$this->pdo->prepare("UPDATE pa_file_object SET status='write_failed',updated_at=UTC_TIMESTAMP(3),revision=revision+1 WHERE tenant_id=:tenant_id AND file_key=:file_key AND status='pending_write'");
        $s->execute(['tenant_id'=>$tenantId,'file_key'=>$fileKey]);
    }

    public function archive(int $tenantId,string $fileKey): bool
    {
        $s=$this->pdo->prepare("UPDATE pa_file_object SET status='archived',archived_at=UTC_TIMESTAMP(3),updated_at=UTC_TIMESTAMP(3),revision=revision+1 WHERE tenant_id=:tenant_id AND file_key=:file_key AND status='ready'");
        $s->execute(['tenant_id'=>$tenantId,'file_key'=>$fileKey]); return $s->rowCount()===1;
    }
    public function restore(int $tenantId,string $fileKey): void
    {
        $s=$this->pdo->prepare("UPDATE pa_file_object SET status='ready',archived_at=NULL,updated_at=UTC_TIMESTAMP(3),revision=revision+1 WHERE tenant_id=:tenant_id AND file_key=:file_key AND status='archived'");
        $s->execute(['tenant_id'=>$tenantId,'file_key'=>$fileKey]);
    }

    public function accounts(): array
    {
        $rows=$this->pdo->query('SELECT id,account_key,driver,name,credential_ref,status,created_at,updated_at FROM pa_storage_account ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn(array $r):array=>$this->decode($r),$rows);
    }
    public function spaces(): array
    {
        return $this->pdo->query('SELECT s.*,a.account_key,a.driver FROM pa_storage_space s JOIN pa_storage_account a ON a.id=s.account_id ORDER BY s.id')->fetchAll(PDO::FETCH_ASSOC);
    }
    public function routes(): array
    {
        return $this->pdo->query('SELECT r.*,s.space_key,s.name space_name,a.driver FROM pa_storage_route r JOIN pa_storage_space s ON s.id=r.space_id JOIN pa_storage_account a ON a.id=s.account_id ORDER BY r.route_key')->fetchAll(PDO::FETCH_ASSOC);
    }

    private function objectSelect(): string
    {
        return 'SELECT f.*,a.id account_id,a.account_key,a.driver,a.name account_name,a.credential_ref,a.status account_status,s.space_key,s.name space_name,s.bucket,s.region,s.endpoint,s.access_domain,s.local_path,s.status space_status FROM pa_file_object f JOIN pa_storage_space s ON s.id=f.storage_space_id JOIN pa_storage_account a ON a.id=s.account_id';
    }
    private function decode(array $row): array
    {
        return $row;
    }
}
