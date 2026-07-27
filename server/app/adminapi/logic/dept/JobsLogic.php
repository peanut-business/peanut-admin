<?php
declare(strict_types=1);

namespace app\adminapi\logic\dept;

use app\common\logic\BaseLogic;
use app\common\model\dept\Jobs;

class JobsLogic extends BaseLogic
{
    /** 分页列表：支持 name(模糊) / code(精确) / is_disable 过滤 */
    public static function lists(array $params): array
    {
        $where = [];
        if (!empty($params['name'])) {
            $where[] = ['name', 'like', '%' . $params['name'] . '%'];
        }
        if (!empty($params['code'])) {
            $where[] = ['code', '=', $params['code']];
        }
        if (isset($params['is_disable']) && $params['is_disable'] !== '') {
            $where[] = ['is_disable', '=', (int)$params['is_disable']];
        }

        $pageNo   = max(1, (int)($params['page_no'] ?? 1));
        $pageSize = min(100, max(1, (int)($params['page_size'] ?? 15)));

        $count = Jobs::where($where)->count();
        $lists = Jobs::where($where)
            ->order(['sort' => 'desc', 'id' => 'desc'])
            ->page($pageNo, $pageSize)
            ->select()
            ->toArray();

        return compact('lists', 'count', 'pageNo', 'pageSize');
    }

    /** 全部岗位（供选择器用） */
    public static function all(): array
    {
        return Jobs::where('is_disable', 0)
            ->field('id,name,code')
            ->order(['sort' => 'desc', 'id' => 'desc'])
            ->select()->toArray();
    }

    public static function detail(int $id): array
    {
        return Jobs::findOrEmpty($id)->toArray();
    }

    public static function add(array $params): bool
    {
        if (self::codeExists((string)$params['code'])) {
            self::setError('岗位编码已存在');
            return false;
        }
        try {
            Jobs::create([
                'name'       => (string)$params['name'],
                'code'       => (string)$params['code'],
                'sort'       => (int)($params['sort'] ?? 0),
                'is_disable' => (int)($params['is_disable'] ?? 0),
                'remark'     => (string)($params['remark'] ?? ''),
            ]);
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function edit(array $params): bool
    {
        if (self::codeExists((string)$params['code'], (int)$params['id'])) {
            self::setError('岗位编码已存在');
            return false;
        }
        try {
            Jobs::update([
                'id'         => (int)$params['id'],
                'name'       => (string)$params['name'],
                'code'       => (string)$params['code'],
                'sort'       => (int)($params['sort'] ?? 0),
                'is_disable' => (int)($params['is_disable'] ?? 0),
                'remark'     => (string)($params['remark'] ?? ''),
            ]);
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function delete(int $id): void
    {
        Jobs::destroy($id);
    }

    public static function updateStatus(int $id, int $isDisable): void
    {
        Jobs::update(['id' => $id, 'is_disable' => $isDisable]);
    }

    /** 编码唯一性检查（排除自身；软删除记录不参与，withTrashed 才可见） */
    protected static function codeExists(string $code, int $exceptId = 0): bool
    {
        $q = Jobs::where('code', $code);
        if ($exceptId > 0) {
            $q->where('id', '<>', $exceptId);
        }
        return $q->count() > 0;
    }
}
