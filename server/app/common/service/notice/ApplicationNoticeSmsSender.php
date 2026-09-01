<?php
declare(strict_types=1);

namespace app\common\service\notice;

use app\common\execution\ExecutionContextAccess;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

/** Keeps Tenant-owned provider credentials behind the notification Host. */
final class ApplicationNoticeSmsSender implements NoticeSmsSender
{
    public function __construct(
        private readonly ExecutionContextAccess $contexts,
        private readonly NoticeChannelService $channels,
    ) {}

    public function send(
        TenantContext|TenantSystemContext $context,
        string $mobile,
        string $templateId,
        array $variables,
        ?callable $beforeSend = null
    ): array {
        if ((string) (getenv('APP_ENV') ?: '') === 'development') {
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
            $this->contexts,
            $context,
            $mobile,
            $templateId,
            $variables,
            $beforeSend,
        );
    }
}
