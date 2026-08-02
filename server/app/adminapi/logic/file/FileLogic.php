<?php
declare(strict_types=1);

namespace app\adminapi\logic\file;

use app\common\enum\FileEnum;
use app\common\logic\BaseLogic;
use app\common\model\file\File;
use app\common\model\file\FileCate;
use app\common\service\storage\Driver;
use think\facade\Db;

class FileLogic extends BaseLogic
{
    /** 分页列表：按 type / 分类子树 / source / name 组合过滤，追加 url。 */
    public static function lists(array $params): array
    {
        $type = self::integerValue($params['type'] ?? null, '文件类型无效');
        if (!FileEnum::isValidType($type)) {
            throw new \InvalidArgumentException('文件类型无效');
        }

        $where = [['type', '=', $type]];
        $categoryIds = null;
        if (array_key_exists('cid', $params) && $params['cid'] !== '') {
            $cid = self::integerValue($params['cid'], '文件分类无效');
            if ($cid < 0) {
                throw new \InvalidArgumentException('文件分类无效');
            }
            $categoryIds = $cid === 0 ? [0] : FileCateLogic::subtreeIds($cid, $type);
        }
        if (array_key_exists('source', $params) && $params['source'] !== '') {
            $source = self::integerValue($params['source'], '上传来源无效');
            if (!in_array($source, [FileEnum::SOURCE_ADMIN, FileEnum::SOURCE_USER], true)) {
                throw new \InvalidArgumentException('上传来源无效');
            }
            $where[] = ['source', '=', $source];
        }
        if (!empty($params['name'])) {
            $where[] = ['name', 'like', '%' . trim((string)$params['name']) . '%'];
        }

        $pageNo   = max(1, (int)($params['page_no'] ?? 1));
        $pageSize = min(100, max(1, (int)($params['page_size'] ?? 15)));

        $countQuery = File::where($where);
        $listQuery = File::where($where);
        if ($categoryIds !== null) {
            $countQuery->whereIn('cid', $categoryIds);
            $listQuery->whereIn('cid', $categoryIds);
        }

        $count = $countQuery->count();
        $lists = $listQuery
            ->append(['url'])
            ->order(['id' => 'desc'])
            ->page($pageNo, $pageSize)
            ->select()
            ->toArray();

        return compact('lists', 'count', 'pageNo', 'pageSize');
    }

    /** 批量移动到分类 */
    public static function move(array $ids, int $cid): void
    {
        $ids = self::normalizeIds($ids);
        $rows = File::whereIn('id', $ids)->select()->toArray();
        if (count($rows) !== count($ids)) {
            throw new \InvalidArgumentException('包含不存在的素材');
        }

        if ($cid < 0) {
            throw new \InvalidArgumentException('目标分类无效');
        }
        if ($cid > 0) {
            $category = FileCate::find($cid);
            if (!$category) {
                throw new \InvalidArgumentException('目标分类不存在');
            }
            $categoryType = (int)$category->type;
            foreach ($rows as $row) {
                if ((int)$row['type'] !== $categoryType) {
                    throw new \InvalidArgumentException('素材类型与目标分类不一致');
                }
            }
        }

        File::whereIn('id', $ids)->update(['cid' => $cid]);
    }

    /** 重命名 */
    public static function rename(int $id, string $name): void
    {
        $name = trim($name);
        if ($id <= 0 || $name === '') {
            throw new \InvalidArgumentException($id <= 0 ? '素材 ID 无效' : '名称不能为空');
        }
        if (mb_strlen($name) > 20) {
            throw new \InvalidArgumentException('名称最多 20 个字符');
        }
        $file = File::find($id);
        if (!$file) {
            throw new \InvalidArgumentException('素材不存在');
        }
        $file->save(['name' => $name]);
    }

    /**
     * 批量删除：每个素材的软删记录与存储删除成对提交；失败立即中止并返回失败。
     * 已完成的前序素材保持已删除，不会留下指向已删除对象的活动记录。
     */
    public static function delete(array $ids): array
    {
        $ids = self::normalizeIds($ids);
        $rows = File::whereIn('id', $ids)->order(['id' => 'asc'])->select();
        if ($rows->count() !== count($ids)) {
            throw new \InvalidArgumentException('包含不存在的素材');
        }

        $deleted = 0;
        foreach ($rows as $row) {
            $fileId = (int)$row['id'];
            $uri = (string)$row['uri'];
            $storage = trim((string)($row['storage'] ?? ''));
            Db::startTrans();
            try {
                if (!$row->delete()) {
                    throw new \RuntimeException('素材记录删除失败');
                }

                if ($storage === '' && str_starts_with(ltrim($uri, '/'), 'storage/')) {
                    $storage = 'local';
                }
                $driver = new Driver($storage !== '' ? $storage : null);
                if (!$driver->delete($uri)) {
                    throw new \RuntimeException($driver->getError() ?: '存储对象删除失败');
                }
                Db::commit();
                $deleted++;
            } catch (\Throwable $e) {
                Db::rollback();
                throw new \RuntimeException('素材 ' . $fileId . ' 删除失败：' . $e->getMessage(), 0, $e);
            }
        }

        return [
            'files_deleted' => $deleted,
            'storage_deleted' => $deleted,
        ];
    }

    private static function normalizeIds(array $ids): array
    {
        $ids = array_map('intval', $ids);
        if (empty($ids)) {
            throw new \InvalidArgumentException('素材 ID 集合不能为空');
        }
        foreach ($ids as $id) {
            if ($id <= 0) {
                throw new \InvalidArgumentException('素材 ID 无效');
            }
        }
        return array_values(array_unique($ids));
    }

    private static function integerValue(mixed $value, string $message): int
    {
        if (!is_int($value) && !(is_string($value) && preg_match('/^-?\d+$/D', $value) === 1)) {
            throw new \InvalidArgumentException($message);
        }
        return (int)$value;
    }
}
