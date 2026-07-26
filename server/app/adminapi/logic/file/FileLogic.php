<?php
declare(strict_types=1);

namespace app\adminapi\logic\file;

use app\common\enum\FileEnum;
use app\common\logic\BaseLogic;
use app\common\model\file\File;
use app\common\service\FileService;

class FileLogic extends BaseLogic
{
    /** 分页列表：按 type / cid / name 过滤，追加 url */
    public static function lists(array $params): array
    {
        $where = [];
        if (!empty($params['type'])) {
            $where[] = ['type', '=', (int)$params['type']];
        }
        if (isset($params['cid']) && $params['cid'] !== '') {
            $where[] = ['cid', '=', (int)$params['cid']];
        }
        if (!empty($params['name'])) {
            $where[] = ['name', 'like', '%' . $params['name'] . '%'];
        }

        $pageNo   = max(1, (int)($params['page_no'] ?? 1));
        $pageSize = min(100, max(1, (int)($params['page_size'] ?? 15)));

        $count = File::where($where)->count();
        $lists = File::where($where)
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
        if (empty($ids)) {
            return;
        }
        File::whereIn('id', $ids)->update(['cid' => $cid]);
    }

    /** 重命名 */
    public static function rename(int $id, string $name): void
    {
        File::update(['id' => $id, 'name' => $name]);
    }

    /** 批量删除：先删本地物理文件，再软删记录 */
    public static function delete(array $ids): void
    {
        if (empty($ids)) {
            return;
        }
        $rows = File::whereIn('id', $ids)->select();
        foreach ($rows as $row) {
            $abs = public_path() . ltrim((string)$row['uri'], '/');
            if (is_file($abs)) {
                @unlink($abs);
            }
        }
        File::destroy($ids);
    }
}
