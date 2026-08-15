<?php
declare(strict_types=1);

namespace app\common\service\notice;

use app\common\model\notice\NoticeLog;
use app\common\model\notice\NoticeScene;
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
        return NoticeScene::where('tenant_id', self::tenantId($context, $operation));
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
        return NoticeLog::where('tenant_id', self::tenantId($context, $operation));
    }

    /** @param array<string,mixed> $data */
    public static function createLog(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        array $data,
        string $operation = ''
    ): NoticeLog
    {
        unset($data['tenant_id']);
        return NoticeLog::create([
            'tenant_id' => self::tenantId($context, $operation),
        ] + $data);
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
