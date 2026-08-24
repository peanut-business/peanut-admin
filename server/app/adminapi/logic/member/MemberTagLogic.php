<?php
declare(strict_types=1);

namespace app\adminapi\logic\member;

use app\common\logic\BaseLogic;
use app\Modules\Official\Member\ModuleProvider;
use PeanutAdmin\Kernel\Auth\TenantContext;

class MemberTagLogic extends BaseLogic
{
    public static function lists(TenantContext $context): array
    {
        return (new ModuleProvider())->queries()->tags($context);
    }

    public static function add(TenantContext $context, array $params): bool
    {
        try {
            (new ModuleProvider())->tagCommands()->create($context, (string)$params['name'], (string)($params['remark'] ?? ''));
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function edit(TenantContext $context, array $params): bool
    {
        try {
            (new ModuleProvider())->tagCommands()->update(
                $context,
                (int)$params['id'],
                (string)$params['name'],
                isset($params['remark']) ? (string)$params['remark'] : null,
            );
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function delete(TenantContext $context, int $id): bool
    {
        try {
            (new ModuleProvider())->tagCommands()->delete($context, $id);
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }
}
