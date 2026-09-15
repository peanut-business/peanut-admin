<?php
declare(strict_types=1);

namespace app\common\contract\dict;

use app\common\dto\dict\DictionaryEntryDto;

interface SystemReferenceCodeQuery
{
    /** @return list<DictionaryEntryDto> */
    public function systemEntriesByType(string $type): array;
}
