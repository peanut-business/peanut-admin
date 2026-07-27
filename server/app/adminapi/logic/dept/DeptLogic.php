<?php
declare(strict_types=1);

namespace app\adminapi\logic\dept;

use app\common\logic\BaseLogic;
use app\common\model\dept\Dept;

class DeptLogic extends BaseLogic
{
    /** 部门树（含全部字段，用于列表） */
    public static function lists(): array
    {
        $data = Dept::order(['sort' => 'desc', 'id' => 'asc'])->select()->toArray();
        return linear_to_tree($data);
    }

    /** 精简部门树（id/pid/name，用于上级选择器） */
    public static function all(): array
    {
        $data = Dept::field('id,pid,name')
            ->order(['sort' => 'desc', 'id' => 'asc'])->select()->toArray();
        return linear_to_tree($data);
    }

    public static function detail(int $id): array
    {
        return Dept::findOrEmpty($id)->toArray();
    }

    public static function add(array $params): bool
    {
        try {
            Dept::create([
                'pid'        => (int)($params['pid'] ?? 0),
                'name'       => (string)$params['name'],
                'leader'     => (string)($params['leader'] ?? ''),
                'mobile'     => (string)($params['mobile'] ?? ''),
                'sort'       => (int)($params['sort'] ?? 0),
                'is_disable' => (int)($params['is_disable'] ?? 0),
            ]);
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function edit(array $params): bool
    {
        // 不能把自己设为自己的上级
        if ((int)($params['pid'] ?? 0) === (int)$params['id']) {
            self::setError('上级部门不能是自己');
            return false;
        }
        try {
            Dept::update([
                'id'         => (int)$params['id'],
                'pid'        => (int)($params['pid'] ?? 0),
                'name'       => (string)$params['name'],
                'leader'     => (string)($params['leader'] ?? ''),
                'mobile'     => (string)($params['mobile'] ?? ''),
                'sort'       => (int)($params['sort'] ?? 0),
                'is_disable' => (int)($params['is_disable'] ?? 0),
            ]);
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function delete(int $id): bool
    {
        if (Dept::where('pid', $id)->count() > 0) {
            self::setError('存在下级部门，不可删除');
            return false;
        }
        Dept::destroy($id);
        return true;
    }

    public static function updateStatus(int $id, int $isDisable): void
    {
        Dept::update(['id' => $id, 'is_disable' => $isDisable]);
    }
}
