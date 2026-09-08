<?php
declare(strict_types=1);

namespace app\common\service\storage;

use PeanutAdmin\FileMedia\Storage\StorageObjectKey;

/** 生成应用拥有的 Tenant 对象命名空间，并委托 Core 做技术路径校验。 */
final class StoragePath
{
    /** 由应用账本身份生成稳定的 Tenant 隔离对象键。 */
    public static function objectKey(int $tenantId, string $purpose, string $fileKey, string $extension): string
    {
        if ($tenantId < 1 || preg_match('/^file_[0-9a-f]{32}$/D', $fileKey) !== 1) {
            throw new \InvalidArgumentException('文件身份无效');
        }
        $directory = str_replace('.', '/', $purpose);
        $extension = strtolower(trim($extension, '.'));
        $suffix = $extension === '' ? '' : '.' . preg_replace('/[^a-z0-9]+/', '', $extension);
        return StorageObjectKey::assert(sprintf(
            'tenants/v1/%d/%s/%s%s',
            $tenantId,
            $directory,
            $fileKey,
            $suffix,
        ));
    }
}
