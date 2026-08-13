<?php
declare(strict_types=1);

namespace app\common\service\dict;

use app\common\model\dict\DictData;
use app\common\model\dict\DictType;
use PeanutAdmin\Kernel\Auth\TenantContext;

final class DictTenantRepository
{
    public static function types(TenantContext $context)
    {
        return DictType::where('tenant_id', DictTenantContext::tenantId($context));
    }

    public static function data(TenantContext $context)
    {
        return DictData::where('tenant_id', DictTenantContext::tenantId($context));
    }

    /** @param array<string,mixed> $values */
    public static function createType(TenantContext $context, array $values): DictType
    {
        unset($values['tenant_id']);
        return DictType::create(['tenant_id' => DictTenantContext::tenantId($context)] + $values);
    }

    /** @param array<string,mixed> $values */
    public static function createData(TenantContext $context, array $values): DictData
    {
        unset($values['tenant_id']);
        return DictData::create(['tenant_id' => DictTenantContext::tenantId($context)] + $values);
    }

    private function __construct()
    {
    }
}
