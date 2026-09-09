<?php
declare(strict_types=1);

namespace app\Modules\Official\Task\Infrastructure\Persistence;

use PDO;
use app\Modules\Official\Task\Contracts\TaskBootstrapCommands;
use app\common\execution\CurrentExecutionContext;

/** Framework-free Task bootstrap adapter for installer and deployment CLI processes. */
final readonly class PdoTaskBootstrapService implements TaskBootstrapCommands
{
    public function __construct(
        private PDO $pdo,
        private CurrentExecutionContext $currentExecution,
    ) {
    }

    public function seedDefaults(array $defaults): void
    {
        $tenantId = $this->tenantId();
        $existingStatement = $this->pdo->prepare(
            'SELECT command FROM pa_crontab WHERE tenant_id = ? AND command = ? AND delete_time IS NULL LIMIT 1'
        );
        $insertStatement = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_crontab
  (tenant_id,name,type,command,params,status,expression,error,last_time,time,max_time,sort,remark,create_time,update_time)
VALUES
  (:tenant_id,:name,:type,:command,:params,:status,:expression,:error,:last_time,:time,:max_time,:sort,:remark,:create_time,:update_time)
SQL);
        foreach ($defaults as $default) {
            $command = (string)($default['command'] ?? '');
            $existingStatement->execute([$tenantId, $command]);
            if ($existingStatement->fetchColumn() !== false) {
                continue;
            }
            $insertStatement->execute([
                'tenant_id' => $tenantId,
                'name' => (string)($default['name'] ?? ''),
                'type' => (int)($default['type'] ?? 1),
                'command' => $command,
                'params' => (string)($default['params'] ?? ''),
                'status' => (int)($default['status'] ?? 2),
                'expression' => (string)($default['expression'] ?? ''),
                'error' => (string)($default['error'] ?? ''),
                'last_time' => (int)($default['last_time'] ?? 0),
                'time' => (float)($default['time'] ?? 0),
                'max_time' => (float)($default['max_time'] ?? 0),
                'sort' => (int)($default['sort'] ?? 0),
                'remark' => (string)($default['remark'] ?? ''),
                'create_time' => (int)($default['create_time'] ?? 0),
                'update_time' => (int)($default['update_time'] ?? 0),
            ]);
        }
    }

    private function tenantId(): int
    {
        $system = $this->currentExecution->system();
        if ($system->tenantId < 1
            || $system->actorKey !== 'platform.tenant-bootstrap'
            || $system->operationId === '') {
            throw new \DomainException('TASK_BOOTSTRAP_CONTEXT_INVALID');
        }
        return $system->tenantId;
    }
}
