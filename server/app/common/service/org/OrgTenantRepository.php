<?php
declare(strict_types=1);

namespace app\common\service\org;

use app\common\persistence\ConvertsModelPage;

final class OrgTenantRepository
{
    use ConvertsModelPage;

    public static function query(string $modelClass)
    {
        return $modelClass::where([]);
    }

    /** @param array<string,mixed> $values */
    public static function create(string $modelClass, array $values)
    {
        unset($values['tenant_id']);
        return $modelClass::create($values);
    }

    /** @param int[] $ids */
    public static function assertOwnedIds(
        string $modelClass,
        array $ids,
        string $message
    ): void {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
        if ($ids !== [] && self::query($modelClass)->whereIn('id', $ids)->count() !== count($ids)) {
            throw new \RuntimeException($message);
        }
    }

    private function __construct()
    {
    }
}
