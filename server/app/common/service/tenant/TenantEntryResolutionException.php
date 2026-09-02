<?php
declare(strict_types=1);

namespace app\common\service\tenant;

final class TenantEntryResolutionException extends \DomainException
{
    public function __construct(\Throwable $previous)
    {
        parent::__construct('TENANT_ENTRY_RESOLUTION_FAILED', 0, $previous);
    }
}
