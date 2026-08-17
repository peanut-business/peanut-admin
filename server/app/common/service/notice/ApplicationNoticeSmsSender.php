<?php
declare(strict_types=1);

namespace app\common\service\notice;

use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

/** Keeps Tenant-owned provider credentials behind the notification Host. */
final class ApplicationNoticeSmsSender implements NoticeSmsSender
{
    public function send(
        TenantContext|TenantSystemContext $context,
        string $mobile,
        string $templateId,
        array $variables,
        ?callable $beforeSend = null
    ): array {
        return NoticeChannelService::sendSms($context, $mobile, $templateId, $variables, $beforeSend);
    }
}
