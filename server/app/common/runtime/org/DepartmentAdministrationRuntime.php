<?php
declare(strict_types=1);

namespace app\common\runtime\org;

use app\common\execution\CurrentExecutionContext;
use PeanutAdmin\Kernel\Organization\Application\DepartmentAdminService;
use think\facade\Db;

/** Owns the Department service assembly and the legacy active/disabled transition. */
final readonly class DepartmentAdministrationRuntime
{
    public function __construct(
        private CurrentExecutionContext $execution,
        private DepartmentAdminService $departments,
    ) {
    }

    public function service(): DepartmentAdminService
    {
        return $this->departments;
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

        Db::transaction(function () use ($context, $department, $target): void {
            $updated = Db::name('department')->where('tenant_id', $context->tenantId)
                ->where('id', (int)($department['id'] ?? 0))
                ->where('revision', (int)($department['revision'] ?? 0))
                ->update([
                    'status' => $target,
                    'revision' => Db::raw('revision + 1'),
                    'updated_at' => Db::raw('CURRENT_TIMESTAMP(3)'),
                ]);
            if ($updated !== 1) {
                throw new \RuntimeException('部门状态已被并发修改');
            }
            Db::name('tenant')->where('id', $context->tenantId)->update([
                'authorization_revision' => Db::raw('authorization_revision + 1'),
                'updated_at' => Db::raw('CURRENT_TIMESTAMP(3)'),
            ]);
        });
    }
}
