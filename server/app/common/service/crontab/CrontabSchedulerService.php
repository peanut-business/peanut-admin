<?php
declare(strict_types=1);

namespace app\common\service\crontab;

use app\common\enum\CrontabEnum;
use app\Modules\Official\Task\Model\Crontab;
use app\common\contract\audit\AuditResource;
use app\common\service\audit\AuditContractHost;
use app\common\execution\ExecutionContextAccess;
use app\common\execution\ExecutionContextStore;
use app\common\tenancy\PlatformTenantDataGateway;
use PeanutAdmin\Kernel\Scheduling\ScheduleWindow;
use PeanutAdmin\Kernel\Tenancy\TenantScope;
use PeanutAdmin\Kernel\Context\TenantSystemContext;
use PeanutAdmin\Kernel\Audit\AuditOutcome;
use Cron\CronExpression;

final class CrontabSchedulerService
{
    /**
     * @param callable(TenantScope,array<string,mixed>):void $trigger
     * @return list<int> active Tenant IDs whose Task workers must be serviced
     */
    public static function runDue(int $now, callable $trigger): array
    {
        $models = (new PlatformTenantDataGateway())
            ->query(Crontab::class, 'scheduler', 'crontab.discover-due')
            ->alias('c')
            ->join('tenant t', 't.id = c.tenant_id')
            ->where('t.status', 'active')
            ->where('c.status', CrontabEnum::START)
            ->field('c.*')
            ->select();

        $tenantIds = [];
        foreach ($models as $model) {
            $item = $model->getData();
            $tenantId = self::positiveInt($item['tenant_id'] ?? null, 'Scheduled job Tenant owner is invalid');
            $tenantIds[$tenantId] = true;
            self::consider($item, $now, $trigger);
        }
        return array_map('intval', array_keys($tenantIds));
    }

    /** @param callable(TenantScope,array<string,mixed>):void $trigger */
    public static function consider(array $item, int $now, callable $trigger): bool
    {
        $tenantId = self::positiveInt($item['tenant_id'] ?? null, 'Scheduled job Tenant owner is invalid');
        $jobId = self::positiveInt($item['id'] ?? null, 'Scheduled job ID is invalid');
        $lastTime = (int)($item['last_time'] ?? 0);
        $scope = TenantScope::fromTrustedContext(
            $tenantId,
            sprintf('crontab:v1:tenant=%d:job=%d:window=%d', $tenantId, $jobId, max(0, $lastTime))
        );

        $system = new TenantSystemContext(
            $tenantId,
            'scheduler',
            'crontab.consider',
            $scope->contextIdentity(),
        );
        return app(ExecutionContextStore::class)->run(
            new \app\common\execution\SystemExecutionContext($system),
            static function () use ($scope, $jobId, $lastTime, $now, $item, $trigger): bool {
                if (!CrontabTenantLock::acquire($scope, $jobId)) {
                    return false;
                }
                try {
            $window = new ScheduleWindow($lastTime, $now);
            if ($window->isInitial()) {
                return self::owned($scope, $jobId)
                    ->where('status', CrontabEnum::START)
                    ->where('last_time', 0)
                    ->update(['last_time' => $now]) === 1;
            }

            try {
                $nextTime = (new CronExpression((string)($item['expression'] ?? '')))
                    ->getNextRunDate(date('Y-m-d H:i:s', $lastTime))
                    ->getTimestamp();
            } catch (\Throwable $exception) {
                self::owned($scope, $jobId)
                    ->where('status', CrontabEnum::START)
                    ->update([
                        'error' => '运行规则错误：' . $exception->getMessage(),
                        'status' => CrontabEnum::ERROR,
                    ]);
                self::audit(
                    $scope,
                    $jobId,
                    'task.crontab.rejected',
                    'crontab.schedule',
                    AuditOutcome::Error,
                    'CRONTAB_EXPRESSION_INVALID',
                );
                return false;
            }

            if (!$window->isDue($nextTime)) {
                return false;
            }

            $claimed = self::owned($scope, $jobId)
                ->where('status', CrontabEnum::START)
                ->where('last_time', $lastTime)
                ->update(['last_time' => $now]);
            if ($claimed !== 1) {
                return false;
            }

            $item['last_time'] = $now;
            $trigger($scope, $item);
            return true;
                } finally {
                    CrontabTenantLock::release($scope, $jobId);
                }
            },
        );
    }

    private static function owned(TenantScope $scope, int $jobId)
    {
        return CrontabTenantRepository::schedules()->where('id', $jobId);
    }

    private static function positiveInt(mixed $value, string $message): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \InvalidArgumentException($message);
        }
        $value = (int)$value;
        if ($value < 1) {
            throw new \InvalidArgumentException($message);
        }
        return $value;
    }

    /** @param array<string,mixed> $metadata */
    private static function audit(
        TenantScope $scope,
        int $jobId,
        string $eventType,
        string $operation,
        AuditOutcome $outcome,
        ?string $reasonCode,
        array $metadata = [],
    ): void {
        $current = ExecutionContextAccess::current();
        if ($current === null || $current->tenantId() !== $scope->tenantId()) {
            throw new \DomainException('CRONTAB_AUDIT_CONTEXT_REQUIRED');
        }
        app(AuditContractHost::class)->recordTenantSystem(
            $scope->tenantId(),
            $eventType,
            $operation,
            $current->requestId(),
            ['job_id' => $jobId] + $metadata,
            $outcome,
            $reasonCode,
            new AuditResource('crontab', (string)$jobId),
        );
    }
}
