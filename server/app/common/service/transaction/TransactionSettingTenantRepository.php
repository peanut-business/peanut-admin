<?php
declare(strict_types=1);

namespace app\common\service\transaction;

use app\common\model\setting\TransactionSetting;

final class TransactionSettingTenantRepository
{
    public static function settings()
    {
        return TransactionSetting::where([]);
    }

    public static function update(array $data): void
    {
        unset($data['tenant_id']);
        $setting = self::settings()->lock(true)->findOrEmpty();
        if ($setting->isEmpty()) {
            TransactionSetting::create($data);
            return;
        }
        $setting->save($data);
    }
}
