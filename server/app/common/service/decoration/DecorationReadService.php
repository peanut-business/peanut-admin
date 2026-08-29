<?php
declare(strict_types=1);

namespace app\common\service\decoration;

use app\common\model\decoration\DecorateTabbar;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

/** 管理端与各客户端共享的只读消费 DTO。 */
class DecorationReadService
{
    public static function pageByType(
        TenantContext|TenantSystemContext $context,
        int $type,
        string $operation = ''
    ): array
    {
        $page = DecorationTenantRepository::pages()
            ->where('type', $type)->findOrEmpty();
        if ($page->isEmpty()) {
            throw new \RuntimeException('装修页面不存在');
        }
        return self::formatPage($page->toArray());
    }

    public static function tabbar(
        TenantContext|TenantSystemContext $context,
        bool $visibleOnly = false,
        string $operation = ''
    ): array {
        $style = DecorationTabbarTenantRepository::readStyle();
        $rows = DecorationTabbarTenantRepository::items()
            ->order(['position' => 'asc', 'id' => 'asc'])->select()->toArray();
        $list = [];
        foreach ($rows as $item) {
            if ($visibleOnly && (int)$item['is_show'] !== 1) {
                continue;
            }
            $item['link'] = json_decode((string)$item['link'], true) ?: [];
            $list[] = DecorationSchemaService::resourcesForRead($item);
        }
        return ['style' => $style, 'list' => $list];
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
