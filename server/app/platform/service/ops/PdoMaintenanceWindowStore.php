<?php
declare(strict_types=1);

namespace app\platform\service\ops;

use app\common\service\audit\AuditContractHost;
use PDO;
use PeanutAdmin\Kernel\Audit\AuditOutcome;
use PeanutAdmin\Kernel\Context\PlatformContext;
use PeanutAdmin\OpsConsole\Application\OpsConsoleException;
use PeanutAdmin\OpsConsole\Maintenance\MaintenanceWindow;
use PeanutAdmin\OpsConsole\Maintenance\MaintenanceWindowStore;
use PeanutAdmin\OpsConsole\Task\OpsAuditEvent;
use Throwable;

/** Application-owned persistence and audit transaction for the Core maintenance contract. */
final readonly class PdoMaintenanceWindowStore implements MaintenanceWindowStore
{
    public function __construct(
        private PDO $pdo,
        private AuditContractHost $audit,
    ) {
    }

    public function current(PlatformContext $context): ?MaintenanceWindow
    {
        $row = $this->one(<<<'SQL'
SELECT maintenance_key, state, reason_key, starts_at, ends_at, revision
FROM pa_ops_maintenance_window
WHERE state IN ('scheduled', 'active')
ORDER BY id DESC
LIMIT 1
SQL);

        return $row === null ? null : $this->window($row);
    }

    public function schedule(
        PlatformContext $context,
        MaintenanceWindow $candidate,
        int $expectedRevision,
        string $idempotencyDigest,
        string $requestDigest,
        OpsAuditEvent $audit,
    ): MaintenanceWindow {
        return $this->transaction(function () use (
            $context,
            $candidate,
            $expectedRevision,
            $idempotencyDigest,
            $requestDigest,
            $audit,
        ): MaintenanceWindow {
            $replayed = $this->one(
                'SELECT * FROM pa_ops_maintenance_window WHERE created_by_operator_id = :operator_id AND idempotency_digest = :idempotency_digest FOR UPDATE',
                ['operator_id' => $context->operatorId, 'idempotency_digest' => $idempotencyDigest],
            );
            if ($replayed !== null) {
                if (!hash_equals((string)$replayed['request_digest'], $requestDigest)) {
                    throw OpsConsoleException::idempotencyConflict();
                }
                return $this->window($replayed);
            }

            $existing = $this->one(
                "SELECT revision FROM pa_ops_maintenance_window WHERE state IN ('scheduled', 'active') ORDER BY id DESC LIMIT 1 FOR UPDATE",
            );
            if (($existing === null && $expectedRevision !== 0)
                || ($existing !== null && (int)$existing['revision'] !== $expectedRevision)
            ) {
                throw OpsConsoleException::revisionConflict();
            }
            if ($existing !== null) {
                throw OpsConsoleException::operationInProgress();
            }

            $insert = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_ops_maintenance_window (
    maintenance_key, state, reason_key, starts_at, ends_at, revision,
    idempotency_digest, request_digest, created_by_operator_id, created_at, updated_at
) VALUES (
    :maintenance_key, :state, :reason_key, :starts_at, :ends_at, :revision,
    :idempotency_digest, :request_digest, :operator_id, UTC_TIMESTAMP(3), UTC_TIMESTAMP(3)
)
SQL);
            $insert->execute([
                'maintenance_key' => $candidate->maintenanceKey,
                'state' => $candidate->state,
                'reason_key' => $candidate->reasonKey,
                'starts_at' => $this->databaseInstant($candidate->startsAt),
                'ends_at' => $this->databaseInstant($candidate->endsAt),
                'revision' => $candidate->revision,
                'idempotency_digest' => $idempotencyDigest,
                'request_digest' => $requestDigest,
                'operator_id' => $context->operatorId,
            ]);
            $this->audit($context, $audit);

            $created = $this->one(
                'SELECT * FROM pa_ops_maintenance_window WHERE maintenance_key = :maintenance_key',
                ['maintenance_key' => $candidate->maintenanceKey],
            );
            if ($created === null) {
                throw OpsConsoleException::internal();
            }
            return $this->window($created);
        });
    }

    public function close(
        PlatformContext $context,
        string $maintenanceKey,
        int $expectedRevision,
        string $idempotencyDigest,
        string $requestDigest,
        OpsAuditEvent $audit,
    ): MaintenanceWindow {
        return $this->transaction(function () use (
            $context,
            $maintenanceKey,
            $expectedRevision,
            $idempotencyDigest,
            $requestDigest,
            $audit,
        ): MaintenanceWindow {
            $current = $this->one(
                'SELECT * FROM pa_ops_maintenance_window WHERE maintenance_key = :maintenance_key FOR UPDATE',
                ['maintenance_key' => $maintenanceKey],
            );
            if ($current === null) {
                throw OpsConsoleException::revisionConflict();
            }
            if ((string)$current['state'] === 'closed'
                && hash_equals((string)$current['idempotency_digest'], $idempotencyDigest)
            ) {
                if (!hash_equals((string)$current['request_digest'], $requestDigest)) {
                    throw OpsConsoleException::idempotencyConflict();
                }
                return $this->window($current);
            }
            if ((int)$current['revision'] !== $expectedRevision || (string)$current['state'] === 'closed') {
                throw OpsConsoleException::revisionConflict();
            }

            $close = $this->pdo->prepare(<<<'SQL'
UPDATE pa_ops_maintenance_window
SET state = 'closed', revision = revision + 1,
    idempotency_digest = :idempotency_digest, request_digest = :request_digest,
    closed_at = UTC_TIMESTAMP(3), updated_at = UTC_TIMESTAMP(3)
WHERE id = :id AND revision = :revision
SQL);
            $close->execute([
                'idempotency_digest' => $idempotencyDigest,
                'request_digest' => $requestDigest,
                'id' => $current['id'],
                'revision' => $expectedRevision,
            ]);
            if ($close->rowCount() !== 1) {
                throw OpsConsoleException::revisionConflict();
            }
            $this->audit($context, $audit);

            $closed = $this->one('SELECT * FROM pa_ops_maintenance_window WHERE id = :id', ['id' => $current['id']]);
            if ($closed === null) {
                throw OpsConsoleException::internal();
            }
            return $this->window($closed);
        });
    }

    /** @param array<string, mixed> $parameters @return array<string, mixed>|null */
    private function one(string $sql, array $parameters = []): ?array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $row */
    private function window(array $row): MaintenanceWindow
    {
        return new MaintenanceWindow(
            (string)$row['maintenance_key'],
            (string)$row['state'],
            (string)$row['reason_key'],
            $this->publicInstant((string)$row['starts_at']),
            $this->publicInstant((string)$row['ends_at']),
            (int)$row['revision'],
        );
    }

    private function audit(PlatformContext $context, OpsAuditEvent $audit): void
    {
        $this->audit->recordPlatform(
            $audit->eventType,
            $audit->action,
            $context->requestId,
            $context->operatorId,
            $context->accountId,
            $audit->metadata,
            AuditOutcome::Success,
            null,
        );
    }

    private function transaction(callable $operation): MaintenanceWindow
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $result = $operation();
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            return $result;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function databaseInstant(string $value): string
    {
        return (new \DateTimeImmutable($value))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
    }

    private function publicInstant(string $value): string
    {
        $normalized = str_replace(' ', 'T', trim($value));
        if (!str_contains($normalized, '.')) {
            $normalized .= '.000';
        }
        return $normalized . 'Z';
    }
}
