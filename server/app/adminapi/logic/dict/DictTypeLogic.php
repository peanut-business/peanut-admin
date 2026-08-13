<?php
declare(strict_types=1);

namespace app\adminapi\logic\dict;

use app\common\logic\BaseLogic;
use app\common\service\dict\DictTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;
use think\facade\Db;

class DictTypeLogic extends BaseLogic
{
    /** 分页列表：支持 name(模糊) / type(模糊) / is_disable 过滤 */
    public static function lists(TenantContext $context, array $params): array
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

        $query = DictTenantRepository::types($context)->where($where);
        $count = (clone $query)->count();
        $lists = $query
            ->order(['id' => 'desc'])
            ->page($pageNo, $pageSize)
            ->select()
            ->toArray();

        return compact('lists', 'count', 'pageNo', 'pageSize');
    }

    /** 全部启用类型（供选择器用） */
    public static function all(TenantContext $context): array
    {
        return DictTenantRepository::types($context)->where('is_disable', 0)
            ->field('id,name,type')
            ->order(['id' => 'desc'])
            ->select()->toArray();
    }

    public static function detail(TenantContext $context, int $id): array
    {
        return DictTenantRepository::types($context)->where('id', $id)->findOrEmpty()->toArray();
    }

    public static function add(TenantContext $context, array $params): bool
    {
        if (self::typeExists($context, (string)$params['type'])) {
            self::setError('字典类型标识已存在');
            return false;
        }
        try {
            DictTenantRepository::createType($context, [
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

    public static function edit(TenantContext $context, array $params): bool
    {
        if (self::typeExists($context, (string)$params['type'], (int)$params['id'])) {
            self::setError('字典类型标识已存在');
            return false;
        }
        try {
            Db::transaction(function () use ($context, $params): void {
                $type = DictTenantRepository::types($context)
                    ->where('id', (int)$params['id'])
                    ->lock(true)
                    ->findOrEmpty();
                if ($type->isEmpty()) {
                    throw new \RuntimeException('字典类型不存在');
                }
                $type->name = (string)$params['name'];
                $type->type = (string)$params['type'];
                $type->is_disable = (int)($params['is_disable'] ?? 0);
                $type->remark = (string)($params['remark'] ?? '');
                $type->save();
                DictTenantRepository::data($context)->where('type_id', (int)$type->id)
                    ->update(['type_value' => (string)$type->type]);
            });
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    /** 被数据占用时拒绝删除，避免无意级联丢失业务枚举。 */
    public static function delete(TenantContext $context, int $id): bool
    {
        try {
            Db::transaction(function () use ($context, $id): void {
                $type = DictTenantRepository::types($context)->where('id', $id)->lock(true)->findOrEmpty();
                if ($type->isEmpty()) {
                    throw new \RuntimeException('字典类型不存在');
                }
                if (!DictTenantRepository::data($context)
                    ->where('type_id', $id)
                    ->lock(true)
                    ->findOrEmpty()
                    ->isEmpty()) {
                    throw new \RuntimeException('字典类型已被数据项使用，请先删除数据项');
                }
                $type->delete();
            });
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function updateStatus(TenantContext $context, int $id, int $isDisable): bool
    {
        $type = DictTenantRepository::types($context)->where('id', $id)->findOrEmpty();
        if ($type->isEmpty()) {
            self::setError('字典类型不存在');
            return false;
        }
        $type->is_disable = $isDisable;
        $type->save();
        return true;
    }

    /** 类型标识唯一性检查（排除自身；软删除记录不参与） */
    protected static function typeExists(TenantContext $context, string $type, int $exceptId = 0): bool
    {
        $q = DictTenantRepository::types($context)->where('type', $type);
        if ($exceptId > 0) {
            $q->where('id', '<>', $exceptId);
        }
        return $q->count() > 0;
    }
}
