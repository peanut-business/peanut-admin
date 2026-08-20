<?php
declare(strict_types=1);

namespace app\common\service\crontab;

use app\common\model\Crontab;
use PeanutAdmin\Kernel\Tenancy\TenantScope;
use PeanutAdmin\Kernel\Auth\TenantContext;

final class CrontabTenantRepository
{
    public static function schedules(TenantContext|TenantScope $context)
    {
        return Crontab::where('tenant_id', CrontabTenantContext::tenantId($context));
    }

    public static function find(TenantContext|TenantScope $context, int $id): ?Crontab
    {
        if ($id < 1) {
            return null;
        }
        $row = self::schedules($context)->where('id', $id)->findOrEmpty();
        return $row->isEmpty() ? null : $row;
    }

    public static function create(TenantContext $context, array $data): Crontab
    {
        unset($data['tenant_id']);
        return Crontab::create(['tenant_id' => CrontabTenantContext::tenantId($context)] + $data);
    }
}
