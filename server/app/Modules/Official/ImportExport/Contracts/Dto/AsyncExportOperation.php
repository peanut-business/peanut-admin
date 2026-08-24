<?php
declare(strict_types=1);

namespace app\Modules\Official\ImportExport\Contracts\Dto;

final readonly class AsyncExportOperation
{
    /** @param array<string,mixed> $payload */
    public function __construct(private array $payload)
    {
    }

    /** @return array<string,mixed> */
    public function toPublicArray(): array
    {
        return $this->payload;
    }
}
