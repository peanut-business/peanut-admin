<?php
declare(strict_types=1);

namespace app\common\service\tenant;

use PDO;

/** Instance-owned read boundary for public Tenant identity projection. */
final readonly class TenantIdentityQuery
{
    public function __construct(private PDO $pdo)
    {
    }

    public function activeName(int $tenantId): string
    {
        if ($tenantId < 1) {
            return '';
        }
        $statement = $this->pdo->prepare(
            "SELECT name FROM pa_tenant WHERE id=? AND status='active' LIMIT 1"
        );
        $statement->execute([$tenantId]);
        return trim((string)$statement->fetchColumn());
    }
}
