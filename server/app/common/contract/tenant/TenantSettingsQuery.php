<?php
declare(strict_types=1);

namespace app\common\contract\tenant;

use app\common\service\member\AuthenticatedMemberContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

interface TenantSettingsQuery
{
    /** @param array<string, mixed> $defaults */
    public function get(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        string $namespace,
        array $defaults = [],
    ): TenantSettingSnapshot;
}
