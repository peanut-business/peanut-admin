<?php
declare(strict_types=1);

namespace app\common\service\notice;

use app\common\execution\CurrentExecutionContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

/** Keeps Tenant-owned provider credentials behind the notification Host. */
final class ApplicationNoticeSmsSender implements NoticeSmsSender
{
    public function __construct(
        private readonly CurrentExecutionContext $executionContext,
        private readonly NoticeChannelService $channels,
        private readonly bool $developmentMode,
    ) {}

    public function send(
        TenantContext|TenantSystemContext $context,
        string $mobile,
        string $templateId,
        array $variables,
        ?callable $beforeSend = null
    ): array {
        if ($this->developmentMode) {
            if ($beforeSend !== null) {
                $beforeSend('development');
            }

            return [
                'success' => true,
                'provider' => 'development',
                'error' => '',
                'result' => ['delivery' => 'simulated'],
            ];
        }

        return $this->channels->sendSms(
            $this->executionContext,
            $context,
            $mobile,
            $templateId,
            $variables,
            $beforeSend,
        );
    }
}
