<?php
declare(strict_types=1);

namespace app\adminapi\logic\decoration;

use app\common\logic\BaseLogic;
use app\common\model\decoration\DecorateTabbar;
use app\common\service\ConfigService;
use app\common\service\decoration\DecorationReadService;
use app\common\service\decoration\DecorationSchemaService;
use think\facade\Db;
use PeanutAdmin\Kernel\Auth\TenantContext;

class DecorationTabbarLogic extends BaseLogic
{
    public static function detail(): array
    {
        return DecorationReadService::tabbar(false);
    }

    public static function save(TenantContext $context, array $style, array $items): bool
    {
        try {
            DecorationSchemaService::validateTabbar($context, $style, $items);
            Db::transaction(function () use ($style, $items): void {
                ConfigService::set('tabbar', 'style', $style);
                DecorateTabbar::where('id', '>', 0)->delete();
                foreach ($items as $position => $item) {
                    $item = DecorationSchemaService::resourcesForStorage($item);
                    DecorateTabbar::create([
                        'position' => $position,
                        'name' => trim((string)$item['name']),
                        'selected' => (string)$item['selected'],
                        'unselected' => (string)$item['unselected'],
                        'link' => json_encode($item['link'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                        'is_show' => (int)$item['is_show'],
                    ]);
                }
            });
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }
}
