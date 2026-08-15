<?php
declare(strict_types=1);

namespace app\common\service\external;

use think\facade\Log;

final class ThinkPhpExternalTenantAudit implements ExternalTenantAudit
{
    public function record(string $outcome, array $attributes): void
    {
        Log::info('external_tenant_resolution', [
            'outcome' => $outcome,
            ...$attributes,
        ]);
    }
}
