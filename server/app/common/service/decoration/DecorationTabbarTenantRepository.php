<?php
declare(strict_types=1);

namespace app\common\service\decoration;

use app\common\model\decoration\DecorateTabbar;
use app\common\model\decoration\DecorationTabbarSetting;

final class DecorationTabbarTenantRepository
{
    private const DEFAULT_STYLE = [
        'default_color' => '#666666',
        'selected_color' => '#2F80ED',
    ];

    public static function items()
    {
        return DecorateTabbar::where([]);
    }

    public static function settings()
    {
        return DecorationTabbarSetting::where([]);
    }

    public static function readStyle(): array
    {
        $raw = self::settings()->value('style');
        if ($raw === null) {
            return self::DEFAULT_STYLE;
        }
        $style = json_decode((string)$raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($style) || array_is_list($style)) {
            throw new \RuntimeException('Tabbar 样式配置无效');
        }
        return $style;
    }

    /** @param list<array<string,mixed>> $items */
    public static function replace(array $style, array $items): void
    {
        $setting = self::settings()->lock(true)->findOrEmpty();
        $storedStyle = json_encode(
            $style,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        if ($setting->isEmpty()) {
            DecorationTabbarSetting::create(['style' => $storedStyle]);
        } else {
            $setting->style = $storedStyle;
            $setting->save();
        }

        self::items()->delete();
        $rows = [];
        foreach ($items as $position => $item) {
            $rows[] = [
                'position' => $position,
                'name' => trim((string)$item['name']),
                'selected' => (string)$item['selected'],
                'unselected' => (string)$item['unselected'],
                'link' => json_encode(
                    $item['link'],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                ),
                'is_show' => (int)$item['is_show'],
            ];
        }
        if ($rows !== []) {
            (new DecorateTabbar())->saveAll($rows);
        }
    }

    private function __construct()
    {
    }
}
