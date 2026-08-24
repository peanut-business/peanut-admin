<?php
declare(strict_types=1);

namespace app\common\service\storage;

final class StoragePath
{
    public static function assertObjectKey(string $objectKey): string
    {
        $objectKey = trim(str_replace('\\', '/', $objectKey), '/');
        if ($objectKey === ''
            || str_contains($objectKey, '..')
            || preg_match('#^[A-Za-z0-9][A-Za-z0-9/._-]{1,254}$#D', $objectKey) !== 1) {
            throw new \InvalidArgumentException('存储对象路径无效');
        }
        return $objectKey;
    }

    public static function objectKey(int $tenantId, string $purpose, string $fileKey, string $extension): string
    {
        if ($tenantId < 1 || preg_match('/^file_[0-9a-f]{32}$/D', $fileKey) !== 1) {
            throw new \InvalidArgumentException('文件身份无效');
        }
        $directory = str_replace('.', '/', $purpose);
        $extension = strtolower(trim($extension, '.'));
        $suffix = $extension === '' ? '' : '.' . preg_replace('/[^a-z0-9]+/', '', $extension);
        return self::assertObjectKey(sprintf(
            'tenants/v1/%d/%s/%s%s',
            $tenantId,
            $directory,
            $fileKey,
            $suffix,
        ));
    }
}
