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
        NoticeTenantRepository::provisionDefaultScenes(NotificationBootstrapDefaults::scenes());
    }
}
