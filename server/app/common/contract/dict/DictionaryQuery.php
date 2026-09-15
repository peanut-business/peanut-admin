<?php
declare(strict_types=1);

namespace app\common\contract\dict;

use app\common\dto\dict\DictionaryEntryDto;
use app\common\dto\dict\DictionaryPageDto;
use app\common\dto\dict\DictionaryTypeDto;
use PeanutAdmin\Kernel\Auth\TenantContext;

interface DictionaryQuery
{
    /** @param array<string,mixed> $filters */
    public function types(TenantContext $context, array $filters, int $page, int $pageSize): DictionaryPageDto;

    /** @param array<string,mixed> $filters */
    public function entries(TenantContext $context, array $filters, int $page, int $pageSize): DictionaryPageDto;

    public function type(TenantContext $context, int $id): ?DictionaryTypeDto;

    /** @return list<DictionaryTypeDto> */
    public function enabledTypes(TenantContext $context): array;

    public function entry(TenantContext $context, int $id): ?DictionaryEntryDto;

    /** @return list<DictionaryEntryDto> */
    public function enabledByType(TenantContext $context, string $type): array;
}
