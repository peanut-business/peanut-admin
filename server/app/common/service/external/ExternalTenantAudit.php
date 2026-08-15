<?php
declare(strict_types=1);

namespace app\common\service\external;

interface ExternalTenantAudit
{
    /** @param array<string, int|string> $attributes */
    public function record(string $outcome, array $attributes): void;
}
