<?php
declare(strict_types=1);

namespace app\Modules\Official\Task\Infrastructure\Persistence;

use app\Modules\Official\Task\Model\Crontab;
use app\common\enum\CrontabEnum;
use app\common\persistence\ConvertsModelPage;
use app\common\tenancy\PlatformTenantDataGateway;

final class CrontabTenantRepository
{
    /** @param list<array<string,mixed>> $rows */
    public static function createMany(array $rows): void
    {
        (new Crontab())->saveAll($rows);
    }

    use ConvertsModelPage;

    public function __construct(private readonly PlatformTenantDataGateway $tenantData)
    {
    }

    /** @return list<array<string,mixed>> */
    public function dueSchedules(): array
    {
        $models = $this->tenantData
            ->query(Crontab::class, 'scheduler', 'crontab.discover-due')
            ->alias('c')
            ->join('tenant t', 't.id = c.tenant_id')
            ->where('t.status', 'active')
            ->where('c.status', CrontabEnum::START)
            ->field('c.*')
            ->select();

        $rows = [];
        foreach ($models as $model) {
            $rows[] = $model->getData();
        }
        return $rows;
    }

    public function claimInitial(int $jobId, int $now): bool
    {
        return self::schedules()
            ->where('id', $jobId)
            ->where('status', CrontabEnum::START)
            ->where('last_time', 0)
            ->update(['last_time' => $now]) === 1;
    }

    public function rejectInvalid(int $jobId, string $message): void
    {
        self::schedules()
            ->where('id', $jobId)
            ->where('status', CrontabEnum::START)
            ->update(['error' => $message, 'status' => CrontabEnum::ERROR]);
    }

    public function claimDue(int $jobId, int $lastTime, int $now): bool
    {
        return self::schedules()
            ->where('id', $jobId)
            ->where('status', CrontabEnum::START)
            ->where('last_time', $lastTime)
            ->update(['last_time' => $now]) === 1;
    }

    public static function schedules()
    {
        return Crontab::where([]);
    }

    public static function find(int $id): ?Crontab
    {
        if ($id < 1) {
            return null;
        }
        $row = self::schedules()->where('id', $id)->findOrEmpty();
        return $row->isEmpty() ? null : $row;
    }

    public static function create(array $data): Crontab
    {
        unset($data['tenant_id']);
        return Crontab::create($data);
    }
}
