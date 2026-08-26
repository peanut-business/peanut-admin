<?php
declare(strict_types=1);

namespace app\Modules\Official\Notification\Service;

use app\Modules\Official\Notification\ModuleProvider;
use app\common\logic\BaseLogic;
use PeanutAdmin\Kernel\Auth\TenantContext;

class NoticeSceneLogic extends BaseLogic
{
    public static function lists(TenantContext $context): array
    {
        self::clearError();
        return (new ModuleProvider())->queries()->scenes($context);
    }

    public static function detail(TenantContext $context, int $id): array
    {
        self::clearError();
        return (new ModuleProvider())->queries()->sceneDetail($context, $id);
    }

    public static function save(TenantContext $context, array $params): bool
    {
        self::clearError();
        try {
            (new ModuleProvider())->commands()->saveScene($context, $params);
            return true;
        } catch (\Throwable $e) {
            return self::fail($e);
        }
    }
}
