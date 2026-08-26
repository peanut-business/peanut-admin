<?php
declare(strict_types=1);

namespace app\common\service\file;

use app\Modules\Official\File\Model\File;
use app\Modules\Official\File\Model\FileCate;
use app\common\service\member\AuthenticatedMemberContext;
use PeanutAdmin\Kernel\Auth\TenantContext;

final class FileTenantRepository
{
    public static function files(AuthenticatedMemberContext|TenantContext $context)
    {
        return File::where('tenant_id', FileTenantContext::tenantId($context));
    }

    public static function categories(AuthenticatedMemberContext|TenantContext $context)
    {
        return FileCate::where('tenant_id', FileTenantContext::tenantId($context));
    }

    public static function findFile(AuthenticatedMemberContext|TenantContext $context, int $id): ?File
    {
        return self::files($context)->where('id', $id)->find();
    }

    public static function findCategory(AuthenticatedMemberContext|TenantContext $context, int $id): ?FileCate
    {
        return self::categories($context)->where('id', $id)->find();
    }

    public static function createFile(AuthenticatedMemberContext|TenantContext $context, array $data): File
    {
        unset($data['tenant_id']);
        if (preg_match('/^file_[0-9a-f]{32}$/D', (string)($data['file_key'] ?? '')) !== 1) {
            throw new \RuntimeException('文件对象身份无效');
        }
        return File::create(['tenant_id' => FileTenantContext::tenantId($context)] + $data);
    }

    public static function tenantId(AuthenticatedMemberContext|TenantContext $context): int
    {
        return FileTenantContext::tenantId($context);
    }

    public static function createCategory(TenantContext $context, array $data): FileCate
    {
        unset($data['tenant_id']);
        return FileCate::create(['tenant_id' => FileTenantContext::tenantId($context)] + $data);
    }
}
