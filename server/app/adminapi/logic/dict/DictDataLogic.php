<?php
declare(strict_types=1);

namespace app\adminapi\logic\dict;

use app\common\logic\BaseLogic;
use app\common\service\dict\DictTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;

class DictDataLogic extends BaseLogic
{
    /** 分页列表：按 type_id 过滤，支持 name(模糊) / is_disable */
    public static function lists(TenantContext $context, array $params): array
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

        $query = DictTenantRepository::data($context)->where($where);
        $count = (clone $query)->count();
        $lists = $query
            ->order(['sort' => 'desc', 'id' => 'desc'])
            ->page($pageNo, $pageSize)
            ->select()
            ->toArray();

        return compact('lists', 'count', 'pageNo', 'pageSize');
    }

    /** 按类型标识取全部启用数据项（业务前端常用：下拉/枚举） */
    public static function byType(TenantContext $context, string $typeValue): array
    {
        return DictTenantRepository::data($context)->where('type_value', $typeValue)
            ->where('is_disable', 0)
            ->field('id,name,value,sort')
            ->order(['sort' => 'desc', 'id' => 'desc'])
            ->select()->toArray();
    }

    public static function detail(TenantContext $context, int $id): array
    {
        return DictTenantRepository::data($context)->where('id', $id)->findOrEmpty()->toArray();
    }

    public static function add(TenantContext $context, array $params): bool
    {
        $type = DictTenantRepository::types($context)
            ->where('id', (int)$params['type_id'])
            ->findOrEmpty();
        if ($type->isEmpty()) {
            self::setError('字典类型不存在');
            return false;
        }
        try {
            DictTenantRepository::createData($context, [
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

    public static function edit(TenantContext $context, array $params): bool
    {
        try {
            $data = DictTenantRepository::data($context)
                ->where('id', (int)$params['id'])
                ->findOrEmpty();
            if ($data->isEmpty()) {
                throw new \RuntimeException('字典数据不存在');
            }
            $data->name = (string)$params['name'];
            $data->value = (string)$params['value'];
            $data->sort = (int)($params['sort'] ?? 0);
            $data->is_disable = (int)($params['is_disable'] ?? 0);
            $data->remark = (string)($params['remark'] ?? '');
            $data->save();
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function delete(TenantContext $context, int $id): bool
    {
        $data = DictTenantRepository::data($context)->where('id', $id)->findOrEmpty();
        if ($data->isEmpty()) {
            self::setError('字典数据不存在');
            return false;
        }
        $data->delete();
        return true;
    }

    public static function updateStatus(TenantContext $context, int $id, int $isDisable): bool
    {
        $data = DictTenantRepository::data($context)->where('id', $id)->findOrEmpty();
        if ($data->isEmpty()) {
            self::setError('字典数据不存在');
            return false;
        }
        $data->is_disable = $isDisable;
        $data->save();
        return true;
    }
}
