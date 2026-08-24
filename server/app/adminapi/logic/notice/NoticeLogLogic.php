<?php
declare(strict_types=1);

namespace app\adminapi\logic\notice;

use app\Modules\Official\Notification\ModuleProvider;
use app\common\logic\BaseLogic;
use PeanutAdmin\Kernel\Auth\TenantContext;

/**
 * 通知发送日志 Logic（只读）
 */
class NoticeLogLogic extends BaseLogic
{
    /**
     * 列表（分页）
     * @param array<string,mixed> $params
     */
    public static function lists(TenantContext $context, array $params): array
    {
        return (new ModuleProvider())->queries()->logs($context, $params);
    }

    /**
     * 日志详情
     */
    public static function detail(TenantContext $context, int $id): array
    {
        return (new ModuleProvider())->queries()->logDetail($context, $id);
    }
}
