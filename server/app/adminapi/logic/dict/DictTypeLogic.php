<?php
declare(strict_types=1);

namespace app\adminapi\logic\dict;

use app\common\logic\BaseLogic;
use app\common\model\dict\DictType;
use app\common\model\dict\DictData;

class DictTypeLogic extends BaseLogic
{
    /** 分页列表：支持 name(模糊) / type(模糊) / is_disable 过滤 */
    public static function lists(array $params): array
    {
        $where = [];
        if (!empty($params['name'])) {
            $where[] = ['name', 'like', '%' . $params['name'] . '%'];
        }
        if (!empty($params['type'])) {
            $where[] = ['type', 'like', '%' . $params['type'] . '%'];
        }
        if (isset($params['is_disable']) && $params['is_disable'] !== '') {
            $where[] = ['is_disable', '=', (int)$params['is_disable']];
        }

        $pageNo   = max(1, (int)($params['page_no'] ?? 1));
        $pageSize = min(100, max(1, (int)($params['page_size'] ?? 15)));

        $count = DictType::where($where)->count();
        $lists = DictType::where($where)
            ->order(['id' => 'desc'])
            ->page($pageNo, $pageSize)
            ->select()
            ->toArray();

        return compact('lists', 'count', 'pageNo', 'pageSize');
    }

    /** 全部启用类型（供选择器用） */
    public static function all(): array
    {
        return DictType::where('is_disable', 0)
            ->field('id,name,type')
            ->order(['id' => 'desc'])
            ->select()->toArray();
    }

    public static function detail(int $id): array
    {
        return DictType::findOrEmpty($id)->toArray();
    }

    public static function add(array $params): bool
    {
        if (self::typeExists((string)$params['type'])) {
            self::setError('字典类型标识已存在');
            return false;
        }
        try {
            DictType::create([
                'name'       => (string)$params['name'],
                'type'       => (string)$params['type'],
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
        if (self::typeExists((string)$params['type'], (int)$params['id'])) {
            self::setError('字典类型标识已存在');
            return false;
        }
        try {
            DictType::update([
                'id'         => (int)$params['id'],
                'name'       => (string)$params['name'],
                'type'       => (string)$params['type'],
                'is_disable' => (int)($params['is_disable'] ?? 0),
                'remark'     => (string)($params['remark'] ?? ''),
            ]);
            // 级联：类型标识变更时，同步更新其字典数据的冗余 type_value
            DictData::where('type_id', (int)$params['id'])
                ->update(['type_value' => (string)$params['type']]);
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    /** 删除类型：同时软删除其下字典数据 */
    public static function delete(int $id): void
    {
        DictType::destroy($id);
        DictData::where('type_id', $id)->select()->each(function ($row) {
            $row->delete();
        });
    }

    public static function updateStatus(int $id, int $isDisable): void
    {
        DictType::update(['id' => $id, 'is_disable' => $isDisable]);
    }

    /** 类型标识唯一性检查（排除自身；软删除记录不参与） */
    protected static function typeExists(string $type, int $exceptId = 0): bool
    {
        $q = DictType::where('type', $type);
        if ($exceptId > 0) {
            $q->where('id', '<>', $exceptId);
        }
        return $q->count() > 0;
    }
}