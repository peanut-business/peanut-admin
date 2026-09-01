<?php
declare(strict_types=1);

namespace app\Modules\Official\Notification\Infrastructure\Persistence;

use app\Modules\Official\Notification\Model\NoticeLog;
use app\Modules\Official\Notification\Model\NoticeScene;
use app\common\service\member\AuthenticatedMemberContext;
use app\common\execution\ExecutionContextAccess;
use app\common\service\notice\NoticeTenantContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;
use think\facade\Db;

final class NoticeTenantRepository
{
    public static function scenes(
        ExecutionContextAccess $contexts,
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        string $operation = ''
    )
    {
        self::tenantId($contexts, $context, $operation);
        return NoticeScene::where([]);
    }

    public static function templates(ExecutionContextAccess $contexts, TenantContext $context)
    {
        return Db::name('notice_template')->where('tenant_id', NoticeTenantContext::tenantId($contexts, $context));
    }

    public static function logs(
        ExecutionContextAccess $contexts,
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        string $operation = ''
    )
    {
        self::tenantId($contexts, $context, $operation);
        return NoticeLog::where([]);
    }

    /** @param array<string,mixed> $data */
    public static function createLog(
        ExecutionContextAccess $contexts,
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        array $data,
        string $operation = ''
    ): NoticeLog
    {
        self::tenantId($contexts, $context, $operation);
        unset($data['tenant_id']);
        return NoticeLog::create($data);
    }

    private static function tenantId(
        ExecutionContextAccess $contexts,
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        string $operation
    ): int
    {
        return $context instanceof AuthenticatedMemberContext
            ? $context->tenantId
            : ($context instanceof TenantContext
                ? NoticeTenantContext::tenantId($contexts, $context)
                : NoticeTenantContext::verificationTenantId($contexts, $context, $operation));
    }
}
