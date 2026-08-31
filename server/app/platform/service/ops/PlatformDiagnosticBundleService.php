<?php
declare(strict_types=1);

namespace app\platform\service\ops;

use app\common\service\instance\DeploymentMode;
use app\platform\service\module\PdoModuleGovernanceProvider;
use Composer\InstalledVersions;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PeanutAdmin\Kernel\Context\PlatformContext;
use PeanutAdmin\OpsConsole\Application\OpsConsoleException;
use PeanutAdmin\OpsConsole\Logs\RuntimeLogProviderRegistry;
use PeanutAdmin\OpsConsole\Logs\RuntimeLogQuery;
use PeanutAdmin\OpsConsole\Logs\RuntimeLogService;
use PeanutAdmin\OpsConsole\Logs\SafeLogMessageCatalog;
use PeanutAdmin\OpsConsole\Package;
use think\facade\Config;

/** Creates a fixed-schema JSON artifact without reading arbitrary files or raw log messages. */
final readonly class PlatformDiagnosticBundleService
{
    private const ALLOWED_WINDOWS = [60, 360, 1440];
    private const MAX_BYTES = 1048576;

    public function __construct(
        private PDO $pdo,
    ) {
    }

    /** @return array{json:string,sha256:string,filename:string,bytes:int} */
    public function create(PlatformContext $context, int $windowMinutes): array
    {
        if (!in_array($windowMinutes, self::ALLOWED_WINDOWS, true)) {
            throw new \InvalidArgumentException('OPS_DIAGNOSTIC_WINDOW_INVALID');
        }

        $permissions = new PlatformOpsPermissionChecker($this->pdo);
        if (!$permissions->allows($context, Package::READ_PERMISSION)
            || !$permissions->allows($context, Package::LOGS_PERMISSION)) {
            throw OpsConsoleException::denied();
        }

        $zone = new DateTimeZone('UTC');
        $generatedAt = new DateTimeImmutable('now', $zone);
        $since = $generatedAt->modify('-' . $windowMinutes . ' minutes');
        $status = PlatformOpsRuntimeFactory::status($this->pdo)
            ->read($context)
            ->toPublicArray();
        $modules = array_map(
            static fn(object $module): array => $module->toArray(),
            PdoModuleGovernanceProvider::forApplication($this->pdo)
                ->qualification()
                ->installedModules(),
        );
        if (count($modules) > 100) {
            throw new \RuntimeException('OPS_DIAGNOSTIC_MODULE_LIMIT_EXCEEDED');
        }

        $logs = (new RuntimeLogService(
            $permissions,
            new RuntimeLogProviderRegistry([
                new PlatformAuditRuntimeLogProvider($this->pdo, $this->databaseInstant($since)),
            ]),
            new SafeLogMessageCatalog([]),
        ))->read($context, new RuntimeLogQuery('platform.audit', 'info', null, 100))->toPublicArray();

        $mode = DeploymentMode::fromConfiguredValue(Config::get('deployment.mode'));
        $payload = [
            'generated_at' => $this->instant($generatedAt),
            'window' => [
                'minutes' => $windowMinutes,
                'from' => $this->instant($since),
                'to' => $this->instant($generatedAt),
            ],
            'limits' => [
                'maximum_bytes' => self::MAX_BYTES,
                'maximum_modules' => 100,
                'maximum_task_groups' => 200,
                'maximum_log_groups' => 100,
                'maximum_operation_logs' => 100,
            ],
            'redaction' => [
                'raw_log_files' => 'excluded',
                'raw_log_messages' => 'excluded',
                'credentials_and_tokens' => 'excluded',
                'request_headers_and_cookies' => 'excluded',
                'absolute_paths' => 'excluded',
                'personal_and_tenant_records' => 'excluded',
                'operation_log_payloads_and_identity' => 'excluded',
            ],
            'configuration' => [
                'deployment_mode' => $mode?->value ?? 'unconfigured',
                'debug_enabled' => (bool)Config::get('app.app_debug', false),
                'php_version' => PHP_VERSION,
                'core_package_version' => InstalledVersions::isInstalled('peanut-admin/core')
                    ? (InstalledVersions::getPrettyVersion('peanut-admin/core') ?? 'unknown')
                    : 'unknown',
            ],
            'runtime' => $status,
            'modules' => $modules,
            'failed_tasks' => [
                'instance' => $this->failedTaskGroups('pa_ops_task', $since),
                'tenant_aggregate' => $this->failedTaskGroups('pa_task_job', $since),
            ],
            'structured_logs' => [
                'source' => 'platform.audit',
                'items' => $logs['items'],
            ],
            'operation_logs' => [
                'source' => 'tenant.audit',
                'items' => $this->operationLogEvidence($since),
            ],
        ];

        $payloadJson = $this->json($payload);
        $bundle = [
            'schema_version' => 1,
            'checksum_algorithm' => 'sha256',
            'payload_sha256' => hash('sha256', $payloadJson),
            'payload' => $payload,
        ];
        $json = $this->json($bundle) . "\n";
        $bytes = strlen($json);
        if ($bytes > self::MAX_BYTES) {
            throw new \RuntimeException('OPS_DIAGNOSTIC_SIZE_LIMIT_EXCEEDED');
        }
        $sha256 = hash('sha256', $json);

        return [
            'json' => $json,
            'sha256' => $sha256,
            'filename' => sprintf(
                'peanut-admin-diagnostics-%s-%s.json',
                $generatedAt->format('Ymd-His'),
                substr($sha256, 0, 12),
            ),
            'bytes' => $bytes,
        ];
    }

    /** @return list<array{task_type:string,status:string,error_code:string,occurrences:int,last_seen_at:string}> */
    private function failedTaskGroups(string $table, DateTimeImmutable $since): array
    {
        if (!in_array($table, ['pa_ops_task', 'pa_task_job'], true)) {
            throw new \LogicException('OPS_DIAGNOSTIC_TASK_SOURCE_INVALID');
        }
        $statement = $this->pdo->prepare(<<<SQL
SELECT task_type, status, COALESCE(last_error_code, 'TASK_ERROR_UNSPECIFIED') AS error_code,
       COUNT(*) AS occurrences, MAX(updated_at) AS last_seen_at
FROM {$table}
WHERE status = 'dead' AND updated_at >= :since
GROUP BY task_type, status, last_error_code
ORDER BY last_seen_at DESC, task_type ASC
LIMIT 100
SQL);
        $statement->execute(['since' => $this->databaseInstant($since)]);

        $groups = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $taskType = (string)($row['task_type'] ?? '');
            $errorCode = (string)($row['error_code'] ?? '');
            $groups[] = [
                'task_type' => preg_match('/^[a-z][a-z0-9]*(?:[.-][a-z0-9]+)*$/D', $taskType) === 1
                    ? $taskType
                    : 'task.unknown',
                'status' => 'dead',
                'error_code' => preg_match('/^[A-Z][A-Z0-9]*(?:_[A-Z0-9]+)*$/D', $errorCode) === 1
                    ? $errorCode
                    : 'TASK_ERROR_REDACTED',
                'occurrences' => min(1000000, max(1, (int)($row['occurrences'] ?? 1))),
                'last_seen_at' => $this->instant(new DateTimeImmutable(
                    $this->databaseValue((string)($row['last_seen_at'] ?? '')),
                    new DateTimeZone('UTC'),
                )),
            ];
        }
        return $groups;
    }

    /** @return list<array{tenant_id:int,request_id:string,operation_id:?string,operation:string,outcome:string,reason_code:?string,route:string,occurred_at:string}> */
    private function operationLogEvidence(DateTimeImmutable $since): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT tenant_id, request_id, operation_id, action, outcome, reason_code,
       target_resource_id, occurred_at
FROM pa_tenant_audit_event
WHERE event_type = 'admin.operation' AND occurred_at >= :since
ORDER BY occurred_at DESC, id DESC
LIMIT 100
SQL);
        $statement->execute(['since' => $this->databaseInstant($since)]);

        return array_map(fn(array $row): array => [
            'tenant_id' => (int)$row['tenant_id'],
            'request_id' => (string)$row['request_id'],
            'operation_id' => $row['operation_id'] === null ? null : (string)$row['operation_id'],
            'operation' => (string)$row['action'],
            'outcome' => (string)$row['outcome'],
            'reason_code' => $row['reason_code'] === null ? null : (string)$row['reason_code'],
            'route' => (string)$row['target_resource_id'],
            'occurred_at' => $this->instant(new DateTimeImmutable(
                $this->databaseValue((string)$row['occurred_at']),
                new DateTimeZone('UTC'),
            )),
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    private function instant(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z');
    }

    private function databaseInstant(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
    }

    private function databaseValue(string $value): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(?:\.\d{1,6})?$/D', $value) !== 1) {
            throw new \RuntimeException('OPS_DIAGNOSTIC_TASK_TIME_INVALID');
        }
        return $value;
    }

    private function json(array $value): string
    {
        return json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
    }
}
