<?php
declare(strict_types=1);

namespace app\platform\service\ops;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PeanutAdmin\Kernel\Context\PlatformContext;
use PeanutAdmin\OpsConsole\Application\OpsConsoleException;
use PeanutAdmin\OpsConsole\Package;
use Throwable;

/** Read-only projection for recent operations tasks and the latest verified backup pair. */
final readonly class PlatformBackupCenterService
{
    private const TASK_LIMIT = 20;

    public function __construct(private PDO $pdo)
    {
    }

    /** @return array{provider:array<string,mixed>,latest_verified:?array<string,mixed>,latest_restore_verified:?array<string,mixed>,tasks:list<array<string,mixed>>} */
    public function snapshot(PlatformContext $context): array
    {
        if (!(new PlatformOpsPermissionChecker($this->pdo))->allows($context, Package::READ_PERMISSION)) {
            throw OpsConsoleException::denied();
        }

        $descriptor = PlatformOpsRuntimeFactory::backupProviders()
            ->require(PairedBackupProvider::PROVIDER_KEY);
        $provider = [
            'key' => $descriptor->key,
        ];
        $runtime = PlatformOpsRuntimeFactory::status($this->pdo)
            ->read($context)
            ->toPublicArray();
        $runtimeCommit = (string)($runtime['version']['commit'] ?? '');

        return [
            'provider' => $provider,
            'latest_verified' => $this->latestVerified($runtimeCommit),
            'latest_restore_verified' => $this->latestRestoreVerified(),
            'tasks' => $this->recentTasks($context),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function recentTasks(PlatformContext $context): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT task_key
FROM pa_ops_task
WHERE task_type IN (:backup_task_type, :restore_task_type)
ORDER BY id DESC
LIMIT 20
SQL);
        $statement->execute([
            'backup_task_type' => Package::BACKUP_TASK_TYPE,
            'restore_task_type' => Package::RESTORE_TASK_TYPE,
        ]);

        $tasks = [];
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $taskKey) {
            $tasks[] = PlatformOpsRuntimeFactory::tasks($this->pdo)
                ->task($context, (string)$taskKey)
                ->toPublicArray();
        }
        return $tasks;
    }

    /** @return array<string,mixed>|null */
    private function latestVerified(string $runtimeCommit): ?array
    {
        $statement = $this->pdo->query(<<<'SQL'
SELECT backup_reference_key, task_key, provider_key, manifest_sha256,
       source_commit, source_tree, source_release_key,
       consistency_started_at, consistency_completed_at, verified_at, manifest_json
FROM pa_ops_backup_evidence
ORDER BY verified_at DESC, id DESC
LIMIT 1
SQL);
        $row = $statement === false ? false : $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        try {
            $manifestJson = (string)$row['manifest_json'];
            $manifest = PairedBackupManifest::fromJson($manifestJson);
            if (!hash_equals(hash('sha256', $manifest->canonicalJson()), (string)$row['manifest_sha256'])
                || !hash_equals($manifest->backupReferenceKey(), (string)$row['backup_reference_key'])
            ) {
                throw new \RuntimeException('OPS_BACKUP_EVIDENCE_INVALID');
            }
            $verifiedAt = $this->instant((string)$row['verified_at']);
            $ageSeconds = max(0, time() - (new DateTimeImmutable($verifiedAt))->getTimestamp());

            return [
                'backup_reference_key' => (string)$row['backup_reference_key'],
                'task_key' => (string)$row['task_key'],
                'provider_key' => (string)$row['provider_key'],
                'manifest_sha256' => (string)$row['manifest_sha256'],
                'source_commit' => (string)$row['source_commit'],
                'source_tree' => (string)$row['source_tree'],
                'source_release_key' => $row['source_release_key'] === null
                    ? null
                    : (string)$row['source_release_key'],
                'consistency_started_at' => $this->instant((string)$row['consistency_started_at']),
                'consistency_completed_at' => $this->instant((string)$row['consistency_completed_at']),
                'verified_at' => $verifiedAt,
                'age_seconds' => $ageSeconds,
                'source_matches_runtime' => hash_equals((string)$row['source_commit'], $runtimeCommit),
            ];
        } catch (Throwable) {
            throw OpsConsoleException::taskUnavailable();
        }
    }

    /** @return array<string,mixed>|null */
    private function latestRestoreVerified(): ?array
    {
        $statement = $this->pdo->query(<<<'SQL'
SELECT backup_reference_key, target_key, evidence_sha256, table_count,
       schema_migration_count, account_count, tenant_count, tenant_member_count,
       storage_file_count, verified_at
FROM pa_ops_restore_evidence
ORDER BY verified_at DESC, id DESC
LIMIT 1
SQL);
        $row = $statement === false ? false : $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        if ((string)$row['target_key'] !== PairedBackupProvider::RESTORE_TARGET_KEY
            || preg_match('/^[a-f0-9]{64}$/D', (string)$row['evidence_sha256']) !== 1
        ) {
            throw OpsConsoleException::taskUnavailable();
        }
        return [
            'backup_reference_key' => (string)$row['backup_reference_key'],
            'target_key' => (string)$row['target_key'],
            'verified_at' => $this->instant((string)$row['verified_at']),
            'verification_sha256' => (string)$row['evidence_sha256'],
            'table_count' => (int)$row['table_count'],
            'migration_count' => (int)$row['schema_migration_count'],
            'tenant_count' => (int)$row['tenant_count'],
            'account_count' => (int)$row['account_count'],
            'tenant_member_count' => (int)$row['tenant_member_count'],
            'file_count' => (int)$row['storage_file_count'],
        ];
    }

    private function instant(string $value): string
    {
        $normalized = str_replace(' ', 'T', trim($value));
        if (!str_contains($normalized, '.')) {
            $normalized .= '.000';
        }
        $instant = $normalized . 'Z';
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.v\Z', $instant, new DateTimeZone('UTC'));
        if ($parsed === false) {
            throw new \RuntimeException('OPS_BACKUP_EVIDENCE_TIME_INVALID');
        }
        return $instant;
    }
}
