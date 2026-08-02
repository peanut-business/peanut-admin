<?php
declare(strict_types=1);

namespace app\adminapi\logic\setting;

use app\common\logic\BaseLogic;
use app\common\service\ConfigService;
use app\common\service\wechat\OfficialAccountService;

class OfficialAccountMenuLogic extends BaseLogic
{
    private const CONFIG_TYPE = 'oa_setting';

    public static function detail(): array
    {
        $stored = (string)ConfigService::get(self::CONFIG_TYPE, 'menu', '[]');
        $menu = json_decode($stored, true);
        return ['menu' => is_array($menu) ? $menu : []];
    }

    public static function save(array $menu): bool
    {
        try {
            self::store($menu);
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function saveAndPublish(array $menu, ?OfficialAccountService $service = null): bool
    {
        try {
            $service ??= new OfficialAccountService();
            $service->publishMenu(
                (string)ConfigService::get(self::CONFIG_TYPE, 'app_id', ''),
                (string)ConfigService::get(self::CONFIG_TYPE, 'app_secret', ''),
                $menu
            );
            self::store($menu);
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    private static function store(array $menu): void
    {
        $json = json_encode($menu, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        ConfigService::setManyAtomic(self::CONFIG_TYPE, ['menu' => $json]);
    }
}
