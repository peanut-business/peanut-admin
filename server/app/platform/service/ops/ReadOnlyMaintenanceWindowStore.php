<?php
declare(strict_types=1);

namespace app\platform\service\ops;

use LogicException;
use PDO;
use PeanutAdmin\Kernel\Context\PlatformContext;
use PeanutAdmin\OpsConsole\Maintenance\MaintenanceWindow;
use PeanutAdmin\OpsConsole\Maintenance\MaintenanceWindowStore;
use PeanutAdmin\OpsConsole\Task\OpsAuditEvent;

/** PC20 read-only projection; maintenance mutations remain owned by PC40. */
final readonly class ReadOnlyMaintenanceWindowStore implements MaintenanceWindowStore
{
    public function __construct(private PDO $pdo)
    {
    }

    public function current(PlatformContext $context): ?MaintenanceWindow
    {
        $statement = $this->pdo->query(<<<'SQL'
SELECT maintenance_key, state, reason_key, starts_at, ends_at, revision
FROM pa_ops_maintenance_window
WHERE state IN ('scheduled', 'active')
ORDER BY id DESC
LIMIT 1
SQL);
        $row = $statement === false ? false : $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return new MaintenanceWindow(
            (string)$row['maintenance_key'],
            (string)$row['state'],
            (string)$row['reason_key'],
            $this->instant((string)$row['starts_at']),
            $this->instant((string)$row['ends_at']),
            (int)$row['revision']
        );
    }

    public function schedule(
        PlatformContext $context,
        MaintenanceWindow $candidate,
        int $expectedRevision,
        string $idempotencyDigest,
        string $requestDigest,
        OpsAuditEvent $audit,
    ): MaintenanceWindow {
        throw new LogicException('OPS_MAINTENANCE_MUTATION_NOT_AVAILABLE');
    }

    public function close(
        PlatformContext $context,
        string $maintenanceKey,
        int $expectedRevision,
        string $idempotencyDigest,
        string $requestDigest,
        OpsAuditEvent $audit,
    ): MaintenanceWindow {
        throw new LogicException('OPS_MAINTENANCE_MUTATION_NOT_AVAILABLE');
    }

    private function instant(string $value): string
    {
        $normalized = str_replace(' ', 'T', trim($value));
        if (!str_contains($normalized, '.')) {
            $normalized .= '.000';
        }
        return $normalized . 'Z';
    }
}
