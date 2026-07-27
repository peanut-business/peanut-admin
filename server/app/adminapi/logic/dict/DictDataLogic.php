<?php
declare(strict_types=1);

namespace app\adminapi\logic\dict;

use app\common\logic\BaseLogic;
use app\common\model\dict\DictData;
use app\common\model\dict\DictType;

class DictDataLogic extends BaseLogic
{
    /** 分页列表：按 type_id 过滤，支持 name(模糊) / is_disable */
    public static function lists(array $params): array
    {
        $where = [];
        if (!empty($params['type_id'])) {
            $where[] = ['type_id', '=', (int)$params['type_id']];
        }
        if (!empty($params['name'])) {
            $where[] = ['name', 'like', '%' . $params['name'] . '%'];
        }
        if (isset($params['is_disable']) && $params['is_disable'] !== '') {
            $where[] = ['is_disable', '=', (int)$params['is_disable']];
        }

        $pageNo   = max(1, (int)($params['page_no'] ?? 1));
        $pageSize = min(100, max(1, (int)($params['page_size'] ?? 15)));

        $count = DictData::where($where)->count();
        $lists = DictData::where($where)
            ->order(['sort' => 'desc', 'id' => 'desc'])
            ->page($pageNo, $pageSize)
            ->select()
            ->toArray();

        return compact('lists', 'count', 'pageNo', 'pageSize');
    }

    /** 按类型标识取全部启用数据项（业务前端常用：下拉/枚举） */
    public static function byType(string $typeValue): array
    {
        return DictData::where('type_value', $typeValue)
            ->where('is_disable', 0)
            ->field('id,name,value,sort')
            ->order(['sort' => 'desc', 'id' => 'desc'])
            ->select()->toArray();
    }

    public static function detail(int $id): array
    {
        return DictData::findOrEmpty($id)->toArray();
    }

    public static function add(array $params): bool
    {
        $type = DictType::findOrEmpty((int)$params['type_id']);
        if ($type->isEmpty()) {
            self::setError('字典类型不存在');
            return false;
        }
        try {
            DictData::create([
                'name'       => (string)$params['name'],
                'value'      => (string)$params['value'],
                'type_id'    => (int)$params['type_id'],
                'type_value' => (string)$type['type'],
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
        try {
            // type_id / type_value 不在编辑范围内变更，避免跨类型迁移
            DictData::update([
                'id'         => (int)$params['id'],
                'name'       => (string)$params['name'],
                'value'      => (string)$params['value'],
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
        DictData::destroy($id);
    }

    public static function updateStatus(int $id, int $isDisable): void
    {
        DictData::update(['id' => $id, 'is_disable' => $isDisable]);
    }
}