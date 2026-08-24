<?php
declare(strict_types=1);

namespace app\common\service\tenant;

use app\common\contract\tenant\TenantSettingsBootstrapProvider;
use app\common\contract\tenant\TenantSettingsBootstrapCommands;

/** Tenant Settings owner entry point for new-Tenant default documents. */
final readonly class TenantSettingsBootstrapService implements TenantSettingsBootstrapCommands
{
    public function __construct(private TenantSettingsBootstrapProvider $provider)
    {
    }

    /** @param array<string, array<string, mixed>> $documents */
    public function seedDefaults(int $tenantId, array $documents): void
    {
        if ($tenantId < 1) {
            throw new \InvalidArgumentException('租户 ID 无效');
        }
        foreach ($documents as $namespace => $document) {
            TenantSettingsNamespace::assertValid($namespace);
            $this->provider->seedIfMissing($tenantId, $namespace, $document);
        }
    }
}
