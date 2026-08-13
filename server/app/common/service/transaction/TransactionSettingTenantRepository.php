<?php
declare(strict_types=1);

namespace app\common\service\transaction;

use app\common\model\setting\TransactionSetting;
use PeanutAdmin\Kernel\Auth\TenantContext;

final class TransactionSettingTenantRepository
{
    public static function settings(TenantContext $context)
    {
        return TransactionSetting::where(
            'tenant_id',
            TransactionSettingTenantContext::tenantId($context)
        );
    }

    public static function update(TenantContext $context, array $data): void
    {
        unset($data['tenant_id']);
        $setting = self::settings($context)->lock(true)->findOrEmpty();
        if ($setting->isEmpty()) {
            TransactionSetting::create([
                'tenant_id' => TransactionSettingTenantContext::tenantId($context),
            ] + $data);
            return;
        }
        $setting->save($data);
    }
}
