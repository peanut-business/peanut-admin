<?php
declare(strict_types=1);

namespace app\platform\service\ops;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PeanutAdmin\Kernel\Context\PlatformContext;
use PeanutAdmin\OpsConsole\Logs\RuntimeLogProvider;
use PeanutAdmin\OpsConsole\Logs\RuntimeLogQuery;
use PeanutAdmin\OpsConsole\Logs\StructuredLogBatch;
use PeanutAdmin\OpsConsole\Logs\StructuredLogRecord;

/** Bounded, metadata-only projection of platform audit events. */
final readonly class PlatformAuditRuntimeLogProvider implements RuntimeLogProvider
{
    public function __construct(
        private PDO $pdo,
        private string $since,
    ) {
    }

    public function sourceKey(): string
    {
        return 'platform.audit';
    }

    public function read(PlatformContext $context, RuntimeLogQuery $query): StructuredLogBatch
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT event_type, outcome, MAX(occurred_at) AS occurred_at, COUNT(*) AS occurrences
FROM pa_platform_audit_event
WHERE occurred_at >= :since
GROUP BY event_type, outcome
ORDER BY occurred_at DESC, event_type ASC
LIMIT 100
SQL);
        $statement->execute(['since' => $this->since]);

        $records = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $severity = match ((string)($row['outcome'] ?? '')) {
                'success' => 'info',
                'denied' => 'warning',
                'error' => 'error',
                default => 'critical',
            };
            if ($this->rank($severity) < $this->rank($query->minimumSeverity)) {
                continue;
            }
            $records[] = new StructuredLogRecord(
                (string)($row['event_type'] ?? ''),
                $severity,
                'platform.audit',
                $this->instant((string)($row['occurred_at'] ?? '')),
                null,
                min(1000000, max(1, (int)($row['occurrences'] ?? 1))),
            );
            if (count($records) >= $query->pageSize) {
                break;
            }
        }

        return new StructuredLogBatch($records, null);
    }

    private function instant(string $value): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(?:\.\d{1,6})?$/D', $value) !== 1) {
            throw new \RuntimeException('OPS_DIAGNOSTIC_LOG_TIME_INVALID');
        }
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s.v\Z');
    }

    private function rank(string $severity): int
    {
        return match ($severity) {
            'info' => 0,
            'warning' => 1,
            'error' => 2,
            default => 3,
        };
    }
}
