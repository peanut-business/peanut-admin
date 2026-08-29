<?php
declare(strict_types=1);

namespace app\common\service\org;

use app\common\execution\CurrentExecutionContext;
use PDO;
use PeanutAdmin\Kernel\Organization\Application\DepartmentAdminService;

/** Owns the Department service assembly and the legacy active/disabled transition. */
final readonly class DepartmentAdministrationRuntime
{
    public function __construct(
        private PDO $pdo,
        private CurrentExecutionContext $execution,
    ) {
    }

    public function service(): DepartmentAdminService
    {
        return new DepartmentAdminService($this->pdo);
    }

    /** @param array<string,mixed> $department */
    public function setStatus(array $department, int $status): void
    {
        $context = $this->execution->tenantAdmin();
        $target = $status === 1 ? 'active' : 'disabled';
        if (($department['status'] ?? null) === $target) {
            return;
        }
        if (($department['status'] ?? null) === 'archived') {
            throw new \RuntimeException('部门已归档');
        }

        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(<<<'SQL'
UPDATE pa_department
SET status = :status, revision = revision + 1, updated_at = CURRENT_TIMESTAMP(3)
WHERE tenant_id = :tenant_id AND id = :id AND revision = :revision
SQL);
            $statement->execute([
                'status' => $target,
                'tenant_id' => $context->tenantId,
                'id' => (int)($department['id'] ?? 0),
                'revision' => (int)($department['revision'] ?? 0),
            ]);
            if ($statement->rowCount() !== 1) {
                throw new \RuntimeException('部门状态已被并发修改');
            }
            $this->pdo->prepare(<<<'SQL'
UPDATE pa_tenant
SET authorization_revision = authorization_revision + 1, updated_at = CURRENT_TIMESTAMP(3)
WHERE id = ?
SQL)->execute([$context->tenantId]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }
}
