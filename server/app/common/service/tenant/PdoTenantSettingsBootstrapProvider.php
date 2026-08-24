<?php
declare(strict_types=1);

namespace app\common\service\tenant;

use PDO;
use app\common\contract\tenant\TenantSettingsBootstrapProvider;

/** PDO adapter used only while provisioning a new Tenant. */
final readonly class PdoTenantSettingsBootstrapProvider implements TenantSettingsBootstrapProvider
{
    public function __construct(private PDO $pdo)
    {
    }

    public function seedIfMissing(int $tenantId, string $namespace, array $document): void
    {
        TenantSettingsNamespace::assertValid($namespace);
        $statement = $this->pdo->prepare('INSERT IGNORE INTO pa_tenant_setting (tenant_id,namespace,config_json,revision,create_time,update_time) VALUES (?,?,?,?,?,?)');
        $statement->execute([$tenantId, $namespace, json_encode($document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 1, 0, 0]);
    }
}
