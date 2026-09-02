<?php
declare(strict_types=1);

namespace app\Modules\Official\Notification\Infrastructure\Persistence;

use app\Modules\Official\Notification\Model\NoticeLog;
use app\Modules\Official\Notification\Model\NoticeScene;
use PeanutAdmin\Kernel\Context\AuthenticatedMemberContext;
use app\common\execution\ExecutionContextAccess;
use app\common\service\notice\NoticeTenantContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;
use think\facade\Db;
use app\common\persistence\ConvertsModelPage;

final class NoticeTenantRepository
{
    use ConvertsModelPage;

    public const LOG_STATUS_PENDING = NoticeLog::STATUS_PENDING;
    public const LOG_STATUS_SUCCESS = NoticeLog::STATUS_SUCCESS;
    public const LOG_STATUS_FAIL = NoticeLog::STATUS_FAIL;
    public const LOG_CHANNEL_SMS = NoticeLog::CHANNEL_SMS;
    public const LOG_VERIFIED_NO = NoticeLog::VERIFIED_NO;
    public const LOG_VERIFIED_YES = NoticeLog::VERIFIED_YES;

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

    /** @param list<array{0:string,1:string,2:string,3:string}> $scenes */
    public static function provisionDefaultScenes(array $scenes): void
    {
        NoticeScene::provisionDefaults($scenes);
    }

    public static function logQuery(string $alias = '')
    {
        return $alias === '' ? NoticeLog::where([]) : NoticeLog::alias($alias)->where([]);
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
