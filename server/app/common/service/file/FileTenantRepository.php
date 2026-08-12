<?php
declare(strict_types=1);

namespace app\common\service\file;

use app\common\model\file\File;
use app\common\model\file\FileCate;
use PeanutAdmin\Kernel\Auth\TenantContext;

final class FileTenantRepository
{
    public static function files(TenantContext $context)
    {
        return File::where('tenant_id', FileTenantContext::tenantId($context));
    }

    public static function categories(TenantContext $context)
    {
        return FileCate::where('tenant_id', FileTenantContext::tenantId($context));
    }

    public static function findFile(TenantContext $context, int $id): ?File
    {
        return self::files($context)->where('id', $id)->find();
    }

    public static function findCategory(TenantContext $context, int $id): ?FileCate
    {
        return self::categories($context)->where('id', $id)->find();
    }

    public static function createFile(TenantContext $context, array $data): File
    {
        unset($data['tenant_id']);
        FileObjectNamespace::assertOwnedUri($context, (string)($data['uri'] ?? ''));
        return File::create(['tenant_id' => FileTenantContext::tenantId($context)] + $data);
    }

    public static function createCategory(TenantContext $context, array $data): FileCate
    {
        unset($data['tenant_id']);
        return FileCate::create(['tenant_id' => FileTenantContext::tenantId($context)] + $data);
    }
}
