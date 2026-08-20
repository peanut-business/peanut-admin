<?php
declare(strict_types=1);

namespace app\adminapi\logic\dict;

use app\common\logic\BaseLogic;
use app\common\service\dict\DictionaryRuntimeFactory;
use app\common\support\PaginationInput;
use PeanutAdmin\Kernel\Auth\TenantContext;

class DictTypeLogic extends BaseLogic
{
    /** 分页列表：支持 name(模糊) / type(模糊) / is_disable 过滤 */
    public static function lists(TenantContext $context, array $params): array
    {
        $filters = [];
        if (!empty($params['name'])) {
            $filters['name'] = (string) $params['name'];
        }
        if (!empty($params['type'])) {
            $filters['type'] = (string) $params['type'];
        }
        if (isset($params['is_disable']) && $params['is_disable'] !== '') {
            $filters['is_disable'] = (int) $params['is_disable'];
        }

        $pagination = PaginationInput::from($params);

        return DictionaryRuntimeFactory::service()->types(
            $context,
            $filters,
            $pagination->page,
            $pagination->pageSize,
        )->toArray();
    }

    /** 全部启用类型（供选择器用） */
    public static function all(TenantContext $context): array
    {
        return array_map(static function ($type): array {
            return ['id' => $type->id, 'name' => $type->name, 'type' => $type->type];
        }, DictionaryRuntimeFactory::service()->enabledTypes($context));
    }

    public static function detail(TenantContext $context, int $id): array
    {
        $type = DictionaryRuntimeFactory::service()->type($context, $id);
        return $type === null ? [] : $type->toArray();
    }

    public static function add(TenantContext $context, array $params): bool
    {
        try {
            DictionaryRuntimeFactory::service()->createType($context, [
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
        try {
            DictionaryRuntimeFactory::service()->replaceType($context, (int) $params['id'], [
                'name' => (string) $params['name'],
                'type' => (string) $params['type'],
                'is_disable' => (int) ($params['is_disable'] ?? 0),
                'remark' => (string) ($params['remark'] ?? ''),
            ]);
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
            DictionaryRuntimeFactory::service()->deleteType($context, $id);
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function updateStatus(TenantContext $context, int $id, int $isDisable): bool
    {
        try {
            DictionaryRuntimeFactory::service()->setTypeDisabled($context, $id, $isDisable !== 0);
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }
}
