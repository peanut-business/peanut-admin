<?php
declare(strict_types=1);

namespace app\common\service\dict;

use app\common\service\dict\contract\DictionaryQuery;
use app\common\service\dict\contract\SystemReferenceCodeQuery;
use app\common\service\dict\contract\TenantDictionaryCommands;
use app\common\service\dict\dto\DictionaryEntryDto;
use app\common\service\dict\dto\DictionaryPageDto;
use app\common\service\dict\dto\DictionaryTypeDto;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Dictionary\Application\DictionaryService as CoreDictionaryService;
use PeanutAdmin\Kernel\Dictionary\Contract\SystemDictionaryProvider;
use PeanutAdmin\Kernel\Dictionary\DictionaryEntry;
use PeanutAdmin\Kernel\Dictionary\DictionaryPage;
use PeanutAdmin\Kernel\Dictionary\DictionaryType;

final class DictionaryRuntime implements DictionaryQuery, TenantDictionaryCommands, SystemReferenceCodeQuery
{
    public function __construct(
        private CoreDictionaryService $core,
        private SystemDictionaryProvider $system,
    ) {}

    public function types(TenantContext $context, array $filters, int $page, int $pageSize): DictionaryPageDto
    {
        return $this->page($this->core->types($context, $filters, $page, $pageSize));
    }

    public function entries(TenantContext $context, array $filters, int $page, int $pageSize): DictionaryPageDto
    {
        return $this->page($this->core->entries($context, $filters, $page, $pageSize));
    }

    public function type(TenantContext $context, int $id): ?DictionaryTypeDto
    {
        return $this->typeDto($this->core->type($context, $id));
    }

    public function enabledTypes(TenantContext $context): array
    {
        return array_map(fn (DictionaryType $type): DictionaryTypeDto => DictionaryTypeDto::fromCore($type), $this->core->enabledTypes($context));
    }

    public function entry(TenantContext $context, int $id): ?DictionaryEntryDto
    {
        return $this->entryDto($this->core->entry($context, $id));
    }

    public function enabledByType(TenantContext $context, string $type): array
    {
        return array_map(
            fn (array $entry): DictionaryEntryDto => DictionaryEntryDto::fromCore(DictionaryEntry::fromArray($entry, (string) ($entry['source'] ?? 'tenant'))),
            $this->core->enabledByType($context, $type),
        );
    }

    public function systemEntriesByType(string $type): array
    {
        return array_map(
            fn (DictionaryEntry $entry): DictionaryEntryDto => DictionaryEntryDto::fromCore($entry),
            $this->systemProviderEntries($type),
        );
    }

    public function createType(TenantContext $context, array $values): DictionaryTypeDto
    {
        return DictionaryTypeDto::fromCore($this->core->createType($context, $values));
    }

    public function replaceType(TenantContext $context, int $id, array $values): DictionaryTypeDto
    {
        return DictionaryTypeDto::fromCore($this->core->replaceType($context, $id, $values));
    }

    public function deleteType(TenantContext $context, int $id): void
    {
        $this->core->deleteType($context, $id);
    }

    public function setTypeDisabled(TenantContext $context, int $id, bool $disabled): void
    {
        $this->core->setTypeDisabled($context, $id, $disabled);
    }

    public function createEntry(TenantContext $context, array $values): DictionaryEntryDto
    {
        return DictionaryEntryDto::fromCore($this->core->createEntry($context, $values));
    }

    public function replaceEntry(TenantContext $context, int $id, array $values): DictionaryEntryDto
    {
        return DictionaryEntryDto::fromCore($this->core->replaceEntry($context, $id, $values));
    }

    public function deleteEntry(TenantContext $context, int $id): void
    {
        $this->core->deleteEntry($context, $id);
    }

    public function setEntryDisabled(TenantContext $context, int $id, bool $disabled): void
    {
        $this->core->setEntryDisabled($context, $id, $disabled);
    }

    private function page(DictionaryPage $page): DictionaryPageDto
    {
        $items = array_map(
            static fn (DictionaryType|DictionaryEntry $item): DictionaryTypeDto|DictionaryEntryDto
                => $item instanceof DictionaryType ? DictionaryTypeDto::fromCore($item) : DictionaryEntryDto::fromCore($item),
            $page->items,
        );
        return new DictionaryPageDto($items, $page->count, $page->page, $page->pageSize);
    }

    private function typeDto(?DictionaryType $type): ?DictionaryTypeDto
    {
        return $type === null ? null : DictionaryTypeDto::fromCore($type);
    }

    private function entryDto(?DictionaryEntry $entry): ?DictionaryEntryDto
    {
        return $entry === null ? null : DictionaryEntryDto::fromCore($entry);
    }

    /** @return list<DictionaryEntry> */
    private function systemProviderEntries(string $type): array
    {
        return $this->system->enabledEntriesByType($type);
    }
}
