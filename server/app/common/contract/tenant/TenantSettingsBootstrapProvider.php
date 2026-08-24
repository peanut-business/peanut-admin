<?php
declare(strict_types=1);

namespace app\common\contract\tenant;

interface TenantSettingsBootstrapProvider
{
    /** @param array<string, mixed> $document */
    public function seedIfMissing(int $tenantId, string $namespace, array $document): void;
}
