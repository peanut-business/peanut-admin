<?php
declare(strict_types=1);

namespace app\adminapi\logic\dict;

use app\common\logic\BaseLogic;
use app\common\service\dict\DictionaryRuntimeFactory;
use app\common\support\PaginationInput;
use PeanutAdmin\Kernel\Auth\TenantContext;

class DictDataLogic extends BaseLogic
{
    /** 分页列表：按 type_id 过滤，支持 name(模糊) / is_disable */
    public static function lists(TenantContext $context, array $params): array
    {
        $filters = [];
        if (!empty($params['type_id'])) {
            $filters['type_id'] = (int) $params['type_id'];
        }
        if (!empty($params['name'])) {
            $filters['name'] = (string) $params['name'];
        }
        if (isset($params['is_disable']) && $params['is_disable'] !== '') {
            $filters['is_disable'] = (int) $params['is_disable'];
        }

        $pagination = PaginationInput::from($params);

        return DictionaryRuntimeFactory::service()->entries(
            $context,
            $filters,
            $pagination->page,
            $pagination->pageSize,
        )->toArray();
    }

    /** 按类型标识取全部启用数据项（业务前端常用：下拉/枚举） */
    public static function byType(TenantContext $context, string $typeValue): array
    {
        return array_map(
            static fn ($entry): array => $entry->toArray(),
            DictionaryRuntimeFactory::service()->enabledByType($context, $typeValue),
        );
    }

    public static function detail(TenantContext $context, int $id): array
    {
        $data = DictionaryRuntimeFactory::service()->entry($context, $id);
        return $data === null ? [] : $data->toArray();
    }

    public static function add(TenantContext $context, array $params): bool
    {
        try {
            DictionaryRuntimeFactory::service()->createEntry($context, [
                'name'       => (string)$params['name'],
                'value'      => (string)$params['value'],
                'type_id'    => (int)$params['type_id'],
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
            DictionaryRuntimeFactory::service()->replaceEntry($context, (int) $params['id'], [
                'name' => (string) $params['name'],
                'value' => (string) $params['value'],
                'sort' => (int) ($params['sort'] ?? 0),
                'is_disable' => (int) ($params['is_disable'] ?? 0),
                'remark' => (string) ($params['remark'] ?? ''),
            ]);
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function delete(TenantContext $context, int $id): bool
    {
        try {
            DictionaryRuntimeFactory::service()->deleteEntry($context, $id);
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function updateStatus(TenantContext $context, int $id, int $isDisable): bool
    {
        try {
            DictionaryRuntimeFactory::service()->setEntryDisabled($context, $id, $isDisable !== 0);
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }
}
