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
        $result = $page->toArray();
        $data = json_decode((string)$result['data'], true, 512, JSON_THROW_ON_ERROR);
        $meta = trim((string)($result['meta'] ?? '')) === ''
            ? [] : json_decode((string)$result['meta'], true, 512, JSON_THROW_ON_ERROR);
        $result['data'] = DecorationSchemaService::resourcesForRead($data);
        $result['meta'] = DecorationSchemaService::resourcesForRead($meta);
        return $result;
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
}
