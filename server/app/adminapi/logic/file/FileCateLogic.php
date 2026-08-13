<?php
declare(strict_types=1);

namespace app\adminapi\logic\file;

use app\common\enum\FileEnum;
use app\common\logic\BaseLogic;
use app\common\service\file\FileTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;
use think\facade\Db;

class FileCateLogic extends BaseLogic
{
    /** 某类型下的稳定分类树（同级按 id 升序）。 */
    public static function lists(TenantContext $context, int $type): array
    {
        if (!FileEnum::isValidType($type)) {
            throw new \InvalidArgumentException('文件类型无效');
        }
        $categories = FileTenantRepository::categories($context)->where('type', $type)
            ->order(['id' => 'asc'])
            ->select()->toArray();
        return linear_to_tree($categories);
    }

    public static function add(TenantContext $context, array $params): bool
    {
        $name = trim((string)($params['name'] ?? ''));
        if ($name === '') {
            self::setError('分类名称不能为空');
            return false;
        }
        if (!FileEnum::isValidType((int)($params['type'] ?? 0))) {
            self::setError('文件类型无效');
            return false;
        }
        $pid = (int)($params['pid'] ?? 0);
        if ($pid < 0) {
            self::setError('父分类无效');
            return false;
        }
        if ($pid > 0) {
            $parent = FileTenantRepository::findCategory($context, $pid);
            if (!$parent) {
                self::setError('父分类不存在');
                return false;
            }
            if ((int)$parent->type !== (int)$params['type']) {
                self::setError('父分类类型不一致');
                return false;
            }
        }
        try {
            FileTenantRepository::createCategory($context, [
                'pid'  => $pid,
                'type' => (int)$params['type'],
                'name' => $name,
            ]);
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function edit(TenantContext $context, array $params): bool
    {
        $name = trim((string)($params['name'] ?? ''));
        if ($name === '') {
            self::setError('分类名称不能为空');
            return false;
        }
        $category = FileTenantRepository::findCategory($context, (int)($params['id'] ?? 0));
        if (!$category) {
            self::setError('分类不存在');
            return false;
        }
        try {
            $category->save(['name' => $name]);
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    /** 删除分类子树、其中素材及存储对象，并返回三者结果。 */
    public static function delete(TenantContext $context, int $id): array
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('分类 ID 无效');
        }
        $root = FileTenantRepository::findCategory($context, $id);
        if (!$root) {
            throw new \InvalidArgumentException('分类不存在');
        }
        $categoryIds = self::subtreeIds($context, $id, (int)$root->type);
        $fileIds = array_map('intval', FileTenantRepository::files($context)->whereIn('cid', $categoryIds)->column('id'));
        $fileResult = empty($fileIds)
            ? ['files_deleted' => 0, 'storage_deleted' => 0]
            : FileLogic::delete($context, $fileIds);

        Db::startTrans();
        try {
            $categories = FileTenantRepository::categories($context)->whereIn('id', $categoryIds)->select();
            if ($categories->count() !== count($categoryIds)) {
                throw new \RuntimeException('分类记录删除不完整');
            }
            foreach ($categories as $category) {
                if (!$category->delete()) {
                    throw new \RuntimeException('分类记录删除失败');
                }
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        return [
            'categories_deleted' => count($categoryIds),
            'files_deleted' => $fileResult['files_deleted'],
            'storage_deleted' => $fileResult['storage_deleted'],
        ];
    }

    /** 校验根分类类型并返回包含自身的稳定子树 ID。 */
    public static function subtreeIds(TenantContext $context, int $id, int $type): array
    {
        if (!FileEnum::isValidType($type)) {
            throw new \InvalidArgumentException('文件类型无效');
        }
        $categories = FileTenantRepository::categories($context)->order(['id' => 'asc'])
            ->field(['id', 'pid', 'type'])
            ->select()
            ->toArray();
        $children = [];
        $exists = false;
        foreach ($categories as $category) {
            $categoryId = (int)$category['id'];
            $parentId = (int)$category['pid'];
            $children[$parentId][] = $category;
            $exists = $exists || ($categoryId === $id && (int)$category['type'] === $type);
        }
        if (!$exists) {
            throw new \InvalidArgumentException('分类不存在或类型不一致');
        }

        $result = [];
        $queue = [$id];
        while ($queue) {
            $current = array_shift($queue);
            if (in_array($current, $result, true)) {
                throw new \RuntimeException('分类子树存在循环关系');
            }
            $result[] = $current;
            foreach ($children[$current] ?? [] as $child) {
                if ((int)$child['type'] !== $type) {
                    throw new \RuntimeException('分类子树存在跨类型关系');
                }
                $queue[] = (int)$child['id'];
            }
        }
        return $result;
    }
}
