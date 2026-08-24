<?php
declare(strict_types=1);

namespace app\common\service\dict\contract;

use app\common\service\dict\dto\DictionaryEntryDto;

interface SystemReferenceCodeQuery
{
    /** @return list<DictionaryEntryDto> */
    public function systemEntriesByType(string $type): array;
}
