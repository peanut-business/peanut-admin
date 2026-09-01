<?php
declare(strict_types=1);
namespace app\common\service;

use app\common\service\member\AuthenticatedMemberContext;
use app\common\service\storage\StorageService;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

/** Business file references enter the physical storage layer only here. */
final class FileService
{
    public static function getFileUrl(string $reference='',?string $unusedStorage=null):string
    {
        $reference=trim($reference);if($reference==='')return '';
        if(preg_match('#^https?://#i',$reference)===1)return $reference;
        $url=StorageService::fromDefaultConnection()->publicUrl($reference);
        return $url!==''?$url:rtrim((string)request()->domain(),'/').'/'.ltrim($reference,'/');
    }
    public static function setFileUrl(string $value=''):string{return trim($value);}
    public static function setTenantFileUrl(AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,string $value=''):string
    {return StorageService::fromDefaultConnection()->normalizePublicReference($context->tenantId,$value);}
}
