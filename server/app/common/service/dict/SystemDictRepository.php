<?php
declare(strict_types=1);

namespace app\common\service\dict;

use think\facade\Db;

/** Reads deployment-wide immutable reference values; tenant writes never reach this table. */
final class SystemDictRepository
{
    /** @return list<array{id:int,name:string,value:string,sort:int,source:string}> */
    public static function dataByType(string $typeValue): array
    {
        $rows = Db::name('system_dict_data')
            ->alias('d')
            ->join('system_dict_type t', 't.code = d.type_code')
            ->where('d.type_code', $typeValue)
            ->where('d.is_disable', 0)
            ->where('t.is_disable', 0)
            ->field('d.id,d.name,d.value,d.sort')
            ->order(['d.sort' => 'desc', 'd.id' => 'desc'])
            ->select()
            ->toArray();
        foreach ($rows as &$row) {
            $row['source'] = 'system';
        }
        unset($row);
        return $rows;
    }

    private function __construct()
    {
    }
}
