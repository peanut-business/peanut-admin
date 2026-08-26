<?php
declare(strict_types=1);

namespace app\common\service\crontab;

use app\common\enum\CrontabEnum;
use app\Modules\Official\Task\Model\Crontab;
use app\common\service\CrontabCommandService;
use app\common\service\module\ModuleExecutionContext;
use app\platform\service\module\PdoModuleGovernanceProvider;
use PeanutAdmin\Kernel\Scheduling\ScheduleWindow;
use PeanutAdmin\Kernel\Tenancy\ScheduledTenantContext;
use PeanutAdmin\Kernel\Tenancy\TenantScope;
use Cron\CronExpression;
use think\facade\Console;
use think\facade\Db;
use PDO;

final class CrontabSchedulerService
{
    /** @var null|callable(string, array): void */
    private static $dispatcher = null;

    public static function runDue(int $now): void
    {
        $models = Crontab::alias('c')
            ->join('tenant t', 't.id = c.tenant_id')
            ->where('t.status', 'active')
            ->where('c.status', CrontabEnum::START)
            ->field('c.*')
            ->select();

        foreach ($models as $model) {
            self::consider($model->getData(), $now);
        }
    }

    public static function consider(array $item, int $now): bool
    {
        $tenantId = self::positiveInt($item['tenant_id'] ?? null, 'Scheduled job Tenant owner is invalid');
        $jobId = self::positiveInt($item['id'] ?? null, 'Scheduled job ID is invalid');
        $lastTime = (int)($item['last_time'] ?? 0);
        $scope = TenantScope::fromTrustedContext(
            $tenantId,
            sprintf('crontab:v1:tenant=%d:job=%d:window=%d', $tenantId, $jobId, max(0, $lastTime))
        );

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
            self::start($scope, $item);
            return true;
        } finally {
            CrontabTenantLock::release($scope, $jobId);
        }
    }

    public static function start(TenantScope $scope, array $item): void
    {
        $jobId = self::positiveInt($item['id'] ?? null, 'Scheduled job ID is invalid');
        $ownerId = self::positiveInt($item['tenant_id'] ?? null, 'Scheduled job Tenant owner is invalid');
        if ($ownerId !== $scope->tenantId()) {
            throw new \RuntimeException('Scheduled job owner is unavailable');
        }
        $ownedItem = self::owned($scope, $jobId)->where('tenant_id', $ownerId)->findOrEmpty();
        if ($ownedItem->isEmpty()) {
            throw new \RuntimeException('Scheduled job owner is unavailable');
        }
        $item = $ownedItem->getData();

        $startTime = microtime(true);
        try {
            $command = trim((string)($item['command'] ?? ''));
            CrontabCommandService::assertTenantAware($command);
            $moduleKey = CrontabCommandService::moduleKey($command);
            if ($moduleKey === null) {
                throw new \RuntimeException('定时任务所属模块未注册');
            }
            $pdo = Db::connect()->connect();
            if (!$pdo instanceof PDO) {
                throw new \RuntimeException('定时任务数据库不可用');
            }
            $governance = PdoModuleGovernanceProvider::forExecution($pdo);
            $governance->executionGuard('official.task')->assertScheduled(
                ModuleExecutionContext::scheduled('official.task', $scope, 'scheduler.execute'),
            );
            $governance->executionGuard($moduleKey)->assertScheduled(
                ModuleExecutionContext::scheduled($moduleKey, $scope, 'scheduler.execute'),
            );
            $params = (($item['params'] ?? '') !== '') ? explode(' ', (string)$item['params']) : [];
            ScheduledTenantContext::run($scope, static function () use ($command, $params): void {
                if (self::$dispatcher !== null) {
                    (self::$dispatcher)($command, $params);
                    return;
                }
                Console::call($command, $params);
            });
            self::owned($scope, $jobId)->update(['error' => '']);
        } catch (\Throwable $exception) {
            self::owned($scope, $jobId)->update([
                'error' => $exception->getMessage(),
                'status' => CrontabEnum::ERROR,
            ]);
        } finally {
            $useTime = round(microtime(true) - $startTime, 2);
            self::owned($scope, $jobId)->update([
                'time' => $useTime,
                'max_time' => max($useTime, (float)($item['max_time'] ?? 0)),
            ]);
        }
    }

    /** @param null|callable(string, array): void $dispatcher */
    public static function useDispatcherForTest(?callable $dispatcher): void
    {
        self::$dispatcher = $dispatcher;
    }

    private static function owned(TenantScope $scope, int $jobId)
    {
        return CrontabTenantRepository::schedules($scope)->where('id', $jobId);
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
}
