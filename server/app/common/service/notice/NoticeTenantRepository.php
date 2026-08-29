<?php
declare(strict_types=1);

namespace app\common\service\notice;

use app\Modules\Official\Notification\Model\NoticeLog;
use app\Modules\Official\Notification\Model\NoticeScene;
use app\common\service\member\AuthenticatedMemberContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;
use think\facade\Db;

final class NoticeTenantRepository
{
    public static function scenes(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        string $operation = ''
    )
    {
        self::tenantId($context, $operation);
        return NoticeScene::where([]);
    }

    public static function templates(TenantContext $context)
    {
        return Db::name('notice_template')->where('tenant_id', NoticeTenantContext::tenantId($context));
    }

    public static function logs(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        string $operation = ''
    )
    {
        self::tenantId($context, $operation);
        return NoticeLog::where([]);
    }

    /** @param array<string,mixed> $data */
    public static function createLog(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        array $data,
        string $operation = ''
    ): NoticeLog
    {
        self::tenantId($context, $operation);
        unset($data['tenant_id']);
        return NoticeLog::create($data);
    }

    private static function tenantId(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        string $operation
    ): int
    {
        return $context instanceof AuthenticatedMemberContext
            ? $context->tenantId
            : ($context instanceof TenantContext
                ? NoticeTenantContext::tenantId($context)
                : NoticeTenantContext::verificationTenantId($context, $operation));
    }
}
