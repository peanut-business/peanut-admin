<?php
declare(strict_types=1);

namespace app\common\service\authorization;

use PDO;

/** Read boundary for checking whether a menu permission is assigned to a role. */
final readonly class MenuPermissionUsageQuery
{
    public function __construct(private PDO $pdo)
    {
    }

    public function assigned(string $permission): bool
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT COUNT(*)
FROM pa_role_permission rp
JOIN pa_permission p ON p.id = rp.permission_id
WHERE p.`key` = ?
SQL);
        $statement->execute([$permission]);
        return (int)$statement->fetchColumn() > 0;
    }
}
