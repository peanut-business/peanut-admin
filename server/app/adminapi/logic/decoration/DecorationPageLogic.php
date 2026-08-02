<?php
declare(strict_types=1);

namespace app\adminapi\logic\decoration;

use app\common\logic\BaseLogic;
use app\common\model\article\Article;
use app\common\model\decoration\DecoratePage;
use app\common\service\decoration\DecorationSchemaService;
use think\facade\Db;

class DecorationPageLogic extends BaseLogic
{
    public static function lists(array $allowedTypes): array
    {
        return DecoratePage::field(['id', 'type', 'name', 'update_time'])
            ->whereIn('type', $allowedTypes)->order('type', 'asc')->select()->toArray();
    }

    public static function detail(int $id, array $allowedTypes): array|false
    {
        try {
            $page = DecoratePage::findOrEmpty($id);
            if ($page->isEmpty() || !in_array((int)$page->type, $allowedTypes, true)) {
                throw new \RuntimeException('装修页面不存在或无权访问');
            }
            return self::format($page->toArray());
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function detailByType(int $type): array|false
    {
        try {
            $page = DecoratePage::where('type', $type)->findOrEmpty();
            if ($page->isEmpty()) {
                throw new \RuntimeException('装修页面不存在');
            }
            return self::format($page->toArray());
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function save(array $params, array $allowedTypes): bool
    {
        try {
            $type = (int)$params['type'];
            if (!in_array($type, $allowedTypes, true)) {
                throw new \RuntimeException('无权保存该装修页面');
            }
            $data = $params['data'];
            $meta = $params['meta'] ?? [];
            DecorationSchemaService::validatePage($type, $data, $meta);

            Db::transaction(function () use ($params, $type, $data, $meta): void {
                $page = DecoratePage::where('id', (int)$params['id'])->lock(true)->findOrEmpty();
                if ($page->isEmpty()) {
                    throw new \RuntimeException('装修页面不存在');
                }
                if ((int)$page->type !== $type) {
                    throw new \RuntimeException('装修页面类型不可修改');
                }
                $page->data = json_encode(
                    DecorationSchemaService::resourcesForStorage($data),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                );
                $page->meta = json_encode(
                    DecorationSchemaService::resourcesForStorage($meta),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                );
                $page->save();
            });
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function articleOptions(int $limit): array
    {
        return Article::field(['id', 'title', 'image', 'abstract'])
            ->where('is_show', 1)->order('id', 'desc')->limit($limit)
            ->select()->toArray();
    }

    private static function format(array $page): array
    {
        $data = json_decode((string)$page['data'], true, 512, JSON_THROW_ON_ERROR);
        $meta = trim((string)($page['meta'] ?? '')) === ''
            ? [] : json_decode((string)$page['meta'], true, 512, JSON_THROW_ON_ERROR);
        $page['data'] = DecorationSchemaService::resourcesForRead($data);
        $page['meta'] = DecorationSchemaService::resourcesForRead($meta);
        return $page;
    }
}
