<?php
declare(strict_types=1);

namespace app\Modules\Official\Notification\Application;

use app\common\execution\SystemExecutionContext;
use app\Modules\Official\Notification\Contracts\NotificationBootstrapCommands;
use app\Modules\Official\Notification\Infrastructure\Persistence\NoticeTenantRepository;

final class NotificationBootstrapService implements NotificationBootstrapCommands
{
    public function provisionTenantDefaults(SystemExecutionContext $context): void
    {
        $system = $context->system;
        if ($system->tenantId < 1
            || $system->actorKey !== 'platform.tenant-bootstrap'
            || $system->operation !== 'notification.provision-tenant-defaults'
            || $system->operationId === '') {
            throw new \DomainException('NOTIFICATION_PROVISION_CONTEXT_INVALID');
        }
        NoticeTenantRepository::provisionDefaultScenes([
            ['login_code', '登录验证码', '用户使用手机号验证码登录', '您的登录验证码是${code}，五分钟内有效。'],
            ['bind_mobile', '绑定手机验证码', '用户首次绑定手机号', '您的绑定手机验证码是${code}，五分钟内有效。'],
            ['change_mobile', '变更手机验证码', '用户更换已绑定手机号', '您的变更手机验证码是${code}，五分钟内有效。'],
            ['reset_password', '找回密码验证码', '用户通过手机号重置密码', '您的找回密码验证码是${code}，五分钟内有效。'],
        ]);
    }
}
