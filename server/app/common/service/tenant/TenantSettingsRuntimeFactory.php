<?php
declare(strict_types=1);

namespace app\common\service\tenant;

final class TenantSettingsRuntimeFactory
{
    public function __construct(private readonly ThinkPhpTenantSettingsProvider $provider)
    {
    }

    public function service(): TenantSettingService
    {
        return new TenantSettingService($this->provider);
    }
}
