<?php
declare(strict_types=1);

namespace app\common\service\dict;

/** @deprecated Use DictionaryRuntimeFactory and the core SystemDictionaryProvider. */
final class SystemDictRepository
{
    /** @return list<array{id:int,name:string,value:string,sort:int,source:string}> */
    public static function dataByType(string $typeValue): array
    {
        return array_map(
            static fn (\PeanutAdmin\Kernel\Dictionary\DictionaryEntry $entry): array => $entry->toArray(),
            (new ThinkPhpSystemDictionaryProvider())->enabledEntriesByType($typeValue),
        );
    }

    private function __construct()
    {
    }
}
