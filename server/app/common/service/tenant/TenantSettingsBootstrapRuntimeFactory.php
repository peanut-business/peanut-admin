<?php
declare(strict_types=1);

namespace app\common\service\tenant;

use PDO;
use app\common\contract\tenant\TenantSettingsBootstrapCommands;

/** Public provisioning entry point; PDO storage remains internal to Tenant Settings. */
final class TenantSettingsBootstrapRuntimeFactory
{
    public static function forProvisioning(PDO $pdo): TenantSettingsBootstrapCommands
    {
        return new TenantSettingsBootstrapService(new PdoTenantSettingsBootstrapProvider($pdo));
    }

    private function __construct()
    {
    }
}
