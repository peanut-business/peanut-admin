<?php
declare(strict_types=1);

namespace app\common\service\notice;

/** Keeps application-owned provider credentials behind the existing notification Host. */
final class ApplicationNoticeSmsSender implements NoticeSmsSender
{
    public function send(
        string $mobile,
        string $templateId,
        array $variables,
        ?callable $beforeSend = null
    ): array {
        return NoticeChannelService::sendSms($mobile, $templateId, $variables, $beforeSend);
    }
}
