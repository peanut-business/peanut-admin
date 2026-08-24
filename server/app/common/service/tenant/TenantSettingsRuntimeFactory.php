<?php
declare(strict_types=1);

namespace app\common\service\tenant;

final class TenantSettingsRuntimeFactory
{
    public static function service(): TenantSettingService
    {
        return new TenantSettingService(new ThinkPhpTenantSettingsProvider());
    }

    private function __construct()
    {
    }
}
