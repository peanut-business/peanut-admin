<?php
declare(strict_types=1);

namespace app\common\service\decoration;

use app\common\model\decoration\DecoratePage;
use app\common\model\decoration\DecorateTabbar;
use app\common\service\ConfigService;

/** 管理端与各客户端共享的只读消费 DTO。 */
class DecorationReadService
{
    public static function pageByType(int $type): array
    {
        $page = DecoratePage::where('type', $type)->findOrEmpty();
        if ($page->isEmpty()) {
            throw new \RuntimeException('装修页面不存在');
        }
        return self::formatPage($page->toArray());
    }

    public static function tabbar(bool $visibleOnly = false): array
    {
        $style = json_decode((string)ConfigService::get('tabbar', 'style', '{}'), true);
        $rows = DecorateTabbar::order(['position' => 'asc', 'id' => 'asc'])->select()->toArray();
        $list = [];
        foreach ($rows as $item) {
            if ($visibleOnly && (int)$item['is_show'] !== 1) {
                continue;
            }
            $item['link'] = json_decode((string)$item['link'], true) ?: [];
            $list[] = DecorationSchemaService::resourcesForRead($item);
        }
        return ['style' => is_array($style) ? $style : [], 'list' => $list];
    }

    public static function formatPage(array $page): array
    {
        $data = json_decode((string)$page['data'], true, 512, JSON_THROW_ON_ERROR);
        $meta = trim((string)($page['meta'] ?? '')) === ''
            ? [] : json_decode((string)$page['meta'], true, 512, JSON_THROW_ON_ERROR);
        $page['data'] = DecorationSchemaService::resourcesForRead($data);
        $page['meta'] = DecorationSchemaService::resourcesForRead($meta);
        return $page;
    }
}
