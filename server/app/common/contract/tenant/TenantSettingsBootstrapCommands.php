<?php
declare(strict_types=1);

namespace app\common\contract\tenant;

interface TenantSettingsBootstrapCommands
{
    /** @param array<string, array<string, mixed>> $documents */
    public function seedDefaults(int $tenantId, array $documents): void;
}
