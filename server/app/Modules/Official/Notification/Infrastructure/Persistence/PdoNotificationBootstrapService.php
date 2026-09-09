<?php
declare(strict_types=1);

namespace app\Modules\Official\Notification\Infrastructure\Persistence;

use PDO;
use app\Modules\Official\Notification\Application\NotificationBootstrapDefaults;
use app\Modules\Official\Notification\Contracts\NotificationBootstrapCommands;
use app\common\execution\CurrentExecutionContext;
use app\common\execution\SystemExecutionContext;

/** Framework-free Notification bootstrap adapter for installer and deployment CLI processes. */
final readonly class PdoNotificationBootstrapService implements NotificationBootstrapCommands
{
    public function __construct(
        private PDO $pdo,
        private CurrentExecutionContext $currentExecution,
    ) {
    }

    public function provisionTenantDefaults(SystemExecutionContext $context): void
    {
        $system = $context->system;
        if ($this->currentExecution->systemExecution() !== $context
            || $system->tenantId < 1
            || $system->actorKey !== 'platform.tenant-bootstrap'
            || $system->operation !== 'notification.provision-tenant-defaults'
            || $system->operationId === '') {
            throw new \DomainException('NOTIFICATION_PROVISION_CONTEXT_INVALID');
        }
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT IGNORE INTO pa_notice_scene
  (tenant_id,code,name,description,recipient,variables,sms_template_id,sms_content,sms_status,create_time,update_time)
VALUES
  (:tenant_id,:code,:name,:description,'用户',:variables,'',:sms_content,0,0,0)
SQL);
        foreach (NotificationBootstrapDefaults::scenes() as [$code, $name, $description, $content]) {
            $statement->execute([
                'tenant_id' => $system->tenantId,
                'code' => $code,
                'name' => $name,
                'description' => $description,
                'variables' => json_encode(['code'], JSON_THROW_ON_ERROR),
                'sms_content' => $content,
            ]);
        }
    }
}
