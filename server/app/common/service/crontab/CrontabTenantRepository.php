<?php
declare(strict_types=1);

namespace app\common\service\crontab;

use app\Modules\Official\Task\Model\Crontab;

final class CrontabTenantRepository
{
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
