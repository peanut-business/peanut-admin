<?php
declare(strict_types=1);
namespace app\common\service\storage;

final readonly class StorageService
{
    public const PRIVATE_URL_TTL=600;
    public function __construct(private StorageRepository $repository) {}
    public static function fromDefaultConnection():self{return new self(StorageRepository::fromDefaultConnection());}

    public function storePath(int $tenantId,?int $memberId,string $purpose,string $sourcePath,string $originalName,string $mediaType):array
    {
        if($tenantId<1||!is_file($sourcePath)||!is_readable($sourcePath))throw new \InvalidArgumentException('待存储文件无效');
        $access=StoragePurpose::accessType($purpose); $originalName=self::filename($originalName);
        $fileKey='file_'.bin2hex(random_bytes(16));
        $objectKey=StoragePath::objectKey($tenantId,$purpose,$fileKey,(string)pathinfo($originalName,PATHINFO_EXTENSION));
        $route=$this->repository->route($purpose,$access); $driver=StorageDriverFactory::make($route,$route);
        $size=filesize($sourcePath);$sha=hash_file('sha256',$sourcePath);
        if(!is_int($size)||!is_string($sha))throw new \RuntimeException('文件信息读取失败');
        $this->repository->reserveObject(['file_key'=>$fileKey,'tenant_id'=>$tenantId,'purpose'=>$purpose,'access_type'=>$access,'storage_space_id'=>(int)$route['space_id'],'object_key'=>$objectKey,'disposition'=>StoragePurpose::disposition($purpose),'original_name'=>$originalName,'media_type'=>$mediaType!==''?$mediaType:'application/octet-stream','size_bytes'=>$size,'sha256'=>$sha,'created_by_member_id'=>$memberId&&$memberId>0?$memberId:null]);
        try {
            $driver->put($objectKey, $sourcePath);
            if (!$this->repository->markObjectReady($tenantId, $fileKey)) {
                throw new \RuntimeException('文件对象账本未能切换到 ready');
            }
        } catch (\Throwable $error) {
            $deleteFailure = null;
            try {
                $driver->delete($objectKey);
            } catch (\Throwable $exception) {
                $deleteFailure = $exception;
            }
            if (!$this->repository->markObjectWriteFailed($tenantId, $fileKey)) {
                throw new \RuntimeException('文件对象账本未能记录 write_failed', 0, $error);
            }
            if ($deleteFailure !== null) {
                throw new \RuntimeException('文件对象写入失败且补偿删除失败，需按 file_key 清理', 0, $deleteFailure);
            }
            throw $error;
        }
        $object=$this->repository->objectForTenant($tenantId,$fileKey);
        return ['file_key'=>$fileKey,'object_key'=>$objectKey,'access_type'=>$access,'url'=>$this->url($object??throw new \RuntimeException('文件对象记录创建失败')),'original_name'=>$originalName];
    }

    public function publicUrl(string $reference):string
    {if($reference==='')return '';if(preg_match('#^https?://#i',$reference)===1)return $reference;$o=$this->repository->publicObject($reference);return $o?$this->url($o):'';}

    public function normalizePublicReference(int $tenantId,string $reference):string
    {
        $reference=trim($reference);if($reference==='')return '';
        if(preg_match('/^file_[0-9a-f]{32}$/D',$reference)===1){$o=$this->repository->objectForTenant($tenantId,$reference);if(!$o||$o['access_type']!=='public')throw new \RuntimeException('素材对象不属于当前租户');return $reference;}
        $path=preg_match('#^https?://#i',$reference)===1?ltrim((string)(parse_url($reference,PHP_URL_PATH)??''),'/'):ltrim($reference,'/');if(str_starts_with($path,'storage/'))$path=substr($path,8);
        $o=$this->repository->publicObject($path);if($o){if((int)$o['tenant_id']!==$tenantId||($reference!==$this->url($o)&&$path!==(string)$o['object_key']))throw new \RuntimeException('素材对象不属于当前租户');return (string)$o['file_key'];}
        if(preg_match('#^https?://#i',$reference)===1)return $reference;throw new \RuntimeException('素材对象不属于当前租户');
    }

    public function delete(int $tenantId,string $fileKey):void
    {$o=$this->repository->objectForTenant($tenantId,$fileKey);if(!$o)throw new \RuntimeException('文件对象不存在');if(!$this->repository->archive($tenantId,$fileKey))throw new \RuntimeException('文件对象状态更新失败');try{StorageDriverFactory::make($o,$o)->delete((string)$o['object_key']);}catch(\Throwable $e){$this->repository->restore($tenantId,$fileKey);throw $e;}}

    public function accessUrlForTenant(int $tenantId,string $fileKey):string
    {$o=$this->repository->objectForTenant($tenantId,$fileKey);if(!$o)throw new \RuntimeException('文件对象不存在');return $this->url($o);}

    public function authorizedLocalDownload(int $tenantId,string $fileKey,int $expires,string $signature):array
    {if($expires<time()||$expires>time()+630||!hash_equals($this->signature($tenantId,$fileKey,$expires),$signature))throw new \RuntimeException('私有文件链接无效或已过期');$o=$this->repository->objectForTenant($tenantId,$fileKey);if(!$o||$o['access_type']!=='private'||$o['driver']!=='local')throw new \RuntimeException('私有文件不存在');$path=StorageDriverFactory::make($o,$o)->localPath((string)$o['object_key']);if(!$path||!is_file($path))throw new \RuntimeException('私有文件不存在');return ['path'=>$path,'filename'=>(string)$o['original_name']];}

    private function url(array $o):string
    {$d=StorageDriverFactory::make($o,$o);if($o['access_type']==='public')return $d->publicUrl((string)$o['object_key']);if($o['driver']!=='local')return $d->temporaryUrl((string)$o['object_key'],self::PRIVATE_URL_TTL,(string)$o['original_name'],(string)$o['disposition']);$e=time()+self::PRIVATE_URL_TTL;return rtrim((string)request()->domain(),'/').'/api/storage/private?'.http_build_query(['tenant_id'=>(int)$o['tenant_id'],'file_key'=>(string)$o['file_key'],'expires'=>$e,'signature'=>$this->signature((int)$o['tenant_id'],(string)$o['file_key'],$e)]);}
    private function signature(int $tenantId,string $fileKey,int $expires):string{$secret=(string)config('jwt.secret','');if(strlen($secret)<32)throw new \RuntimeException('私有文件签名配置无效');return hash_hmac('sha256',$tenantId.'|'.$fileKey.'|'.$expires,$secret);}
    private static function filename(string $v):string{$v=trim(str_replace(["\0",'/',"\\"],'_',$v));if($v==='')throw new \InvalidArgumentException('文件名无效');return mb_substr($v,0,255);}
}
