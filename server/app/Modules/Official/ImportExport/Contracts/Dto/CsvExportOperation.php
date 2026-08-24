<?php
declare(strict_types=1);

namespace app\Modules\Official\ImportExport\Contracts\Dto;

final readonly class CsvExportOperation
{
    public const OPERATION_LOG_PROVIDER = 'app.operation-log';

    private function __construct(
        public string $providerKey,
        public string $idempotencyKey,
    ) {
    }

    public static function operationLog(string $idempotencyKey): self
    {
        return new self(self::OPERATION_LOG_PROVIDER, $idempotencyKey);
    }
}
