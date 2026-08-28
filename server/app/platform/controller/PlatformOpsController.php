<?php
declare(strict_types=1);

namespace app\platform\controller;

use app\common\service\audit\AuditContractHost;
use app\common\service\JsonService;
use app\platform\http\PlatformRequest;
use app\platform\service\ops\PlatformDiagnosticBundleService;
use app\platform\service\ops\PlatformBackupCenterService;
use app\platform\service\ops\PlatformOpsRuntimeFactory;
use PDO;
use PeanutAdmin\OpsConsole\Application\OpsConsoleException;
use think\facade\Db;
use think\Response;
use think\response\Json;
use Throwable;

/** Platform-only Ops Console Host; PC20 reads plus the bounded PC21 artifact. */
final class PlatformOpsController extends BasePlatformController
{
    public function status(): Json
    {
        return $this->run(fn(PDO $pdo): array => PlatformOpsRuntimeFactory::status($pdo)
            ->read($this->context())
            ->toPublicArray());
    }

    public function upgradeReadiness(): Json
    {
        return $this->run(fn(PDO $pdo): array => PlatformOpsRuntimeFactory::runtimeStatusProvider($pdo)
            ->upgradeReadiness($this->context()));
    }

    public function providers(): Json
    {
        return $this->run(fn(PDO $pdo): array => PlatformOpsRuntimeFactory::providerQualifications($pdo)
            ->snapshot($this->context()));
    }

    public function maintenance(): Json
    {
        return $this->run(fn(PDO $pdo): ?array => PlatformOpsRuntimeFactory::maintenance($pdo)
            ->current($this->context())
            ?->toPublicArray());
    }

    public function scheduleMaintenance(): Json
    {
        return $this->run(function (PDO $pdo): array {
            $params = $this->request->put();
            $keys = array_keys($params);
            sort($keys, SORT_STRING);
            if ($keys !== ['ends_at', 'reason_key', 'starts_at']
                || !is_string($params['reason_key'])
                || !is_string($params['starts_at'])
                || !is_string($params['ends_at'])
            ) {
                throw OpsConsoleException::invalid();
            }

            return PlatformOpsRuntimeFactory::maintenance($pdo)
                ->schedule(
                    $this->context(),
                    $params['reason_key'],
                    $params['starts_at'],
                    $params['ends_at'],
                    $this->ifMatchRevision(true),
                    $this->idempotencyKey(),
                )
                ->toPublicArray();
        });
    }

    public function closeMaintenance(string $maintenance_key): Json
    {
        return $this->run(function (PDO $pdo) use ($maintenance_key): array {
            if ($this->request->post() !== []) {
                throw OpsConsoleException::invalid();
            }

            return PlatformOpsRuntimeFactory::maintenance($pdo)
                ->close(
                    $this->context(),
                    $maintenance_key,
                    $this->ifMatchRevision(false),
                    $this->idempotencyKey(),
                )
                ->toPublicArray();
        });
    }

    public function diagnostics(): Response
    {
        $requestId = PlatformRequest::requestId($this->request);
        try {
            $windowMinutes = $this->windowMinutes($this->request->get('window_minutes', 60));
        } catch (\InvalidArgumentException) {
            return JsonService::fail(
                'Diagnostic window is invalid.',
                ['error_code' => 'OPS_DIAGNOSTIC_WINDOW_INVALID'],
                42200,
            )->header(['Cache-Control' => 'no-store', 'X-Request-Id' => $requestId]);
        }

        try {
            $pdo = Db::connect()->connect();
            if (!$pdo instanceof PDO) {
                throw OpsConsoleException::statusUnavailable();
            }
            $artifact = (new PlatformDiagnosticBundleService($pdo))
                ->create($this->context(), $windowMinutes);
            $context = $this->context();
            AuditContractHost::fromPdo($pdo)->appendPlatform(
                'platform.ops.diagnostics.downloaded',
                'platform.ops.logs.read',
                $requestId,
                $context->operatorId,
                $context->accountId,
                [
                    'artifact_sha256' => $artifact['sha256'],
                    'artifact_bytes' => $artifact['bytes'],
                    'window_minutes' => $windowMinutes,
                ],
            );

            return response($artifact['json'], 200, [
                'Cache-Control' => 'no-store',
                'Content-Disposition' => 'attachment; filename="' . $artifact['filename'] . '"',
                'Content-Length' => (string)$artifact['bytes'],
                'Content-Type' => 'application/json; charset=utf-8',
                'X-Content-Type-Options' => 'nosniff',
                'X-Diagnostic-SHA256' => $artifact['sha256'],
                'X-Request-Id' => $requestId,
            ]);
        } catch (OpsConsoleException $exception) {
            return JsonService::fail(
                'Diagnostic bundle is unavailable.',
                ['error_code' => $exception->problemCode],
                $exception->status * 100,
            )->header(['Cache-Control' => 'no-store', 'X-Request-Id' => $requestId]);
        } catch (Throwable) {
            return JsonService::fail(
                'Diagnostic bundle is unavailable.',
                ['error_code' => 'OPS_DIAGNOSTIC_UNAVAILABLE'],
                50300,
            )->header(['Cache-Control' => 'no-store', 'X-Request-Id' => $requestId]);
        }
    }

    public function backup(): Json
    {
        return $this->run(function (PDO $pdo): array {
            $params = $this->request->post();
            if (array_keys($params) !== ['provider_key'] || !is_string($params['provider_key'])) {
                throw OpsConsoleException::invalid();
            }
            return PlatformOpsRuntimeFactory::tasks($pdo)
                ->submitBackup(
                    $this->context(),
                    $params['provider_key'],
                    $this->idempotencyKey()
                )
                ->toPublicArray();
        });
    }

    public function restore(): Json
    {
        return $this->run(function (PDO $pdo): array {
            $params = $this->request->post();
            $paramKeys = array_keys($params);
            sort($paramKeys, SORT_STRING);
            if ($paramKeys !== ['backup_reference_key', 'provider_key', 'target_key']
                || !is_string($params['provider_key'])
                || !is_string($params['backup_reference_key'])
                || !is_string($params['target_key'])
            ) {
                throw OpsConsoleException::invalid();
            }
            return PlatformOpsRuntimeFactory::tasks($pdo)
                ->submitRestore(
                    $this->context(),
                    $params['provider_key'],
                    $params['backup_reference_key'],
                    $params['target_key'],
                    $this->idempotencyKey()
                )
                ->toPublicArray();
        });
    }

    public function upgrade(): Json
    {
        return $this->run(function (PDO $pdo): array {
            if ($this->request->post() !== []) {
                throw OpsConsoleException::invalid();
            }
            return PlatformOpsRuntimeFactory::upgrades($pdo)
                ->submit($this->context(), $this->idempotencyKey());
        });
    }

    public function moduleOperation(): Json
    {
        return $this->run(function (PDO $pdo): array {
            $params = $this->request->post();
            if (array_keys($params) !== ['request_key'] || !is_string($params['request_key'])) {
                throw OpsConsoleException::invalid();
            }
            return PlatformOpsRuntimeFactory::moduleOperations($pdo)->submit(
                $this->context(),
                $params['request_key'],
                $this->idempotencyKey(),
            );
        });
    }

    public function moduleOperations(): Json
    {
        return $this->run(fn(PDO $pdo): array => PlatformOpsRuntimeFactory::moduleOperations($pdo)
            ->snapshot($this->context()));
    }

    public function upgrades(): Json
    {
        return $this->run(fn(PDO $pdo): array => PlatformOpsRuntimeFactory::upgrades($pdo)
            ->snapshot($this->context()));
    }

    public function backups(): Json
    {
        return $this->run(fn(PDO $pdo): array => (new PlatformBackupCenterService($pdo))
            ->snapshot($this->context()));
    }

    public function task(string $task_key): Json
    {
        return $this->run(function (PDO $pdo) use ($task_key): array {
            $module = PlatformOpsRuntimeFactory::moduleOperations($pdo)
                ->taskIfModuleOperation($this->context(), $task_key);
            $upgrade = PlatformOpsRuntimeFactory::upgrades($pdo)
                ->taskIfUpgrade($this->context(), $task_key);
            return $module ?? $upgrade ?? PlatformOpsRuntimeFactory::tasks($pdo)
                ->task($this->context(), $task_key)
                ->toPublicArray();
        });
    }

    private function run(callable $operation): Json
    {
        try {
            $pdo = Db::connect()->connect();
            if (!$pdo instanceof PDO) {
                throw OpsConsoleException::statusUnavailable();
            }
            $response = JsonService::data($operation($pdo));
        } catch (OpsConsoleException $exception) {
            $response = JsonService::fail(
                'Operations status is unavailable.',
                ['error_code' => $exception->problemCode],
                $exception->status * 100
            );
        } catch (Throwable) {
            $response = JsonService::fail(
                'Operations status is unavailable.',
                ['error_code' => 'OPS_STATUS_UNAVAILABLE'],
                50300
            );
        }

        return $response->header([
            'Cache-Control' => 'no-store',
            'X-Request-Id' => PlatformRequest::requestId($this->request),
        ]);
    }

    private function context(): \PeanutAdmin\Kernel\Context\PlatformContext
    {
        if ($this->platformContext === null) {
            throw OpsConsoleException::denied();
        }
        return $this->platformContext->core;
    }

    private function windowMinutes(mixed $value): int
    {
        $candidate = is_int($value) ? (string)$value : trim((string)$value);
        if (!in_array($candidate, ['60', '360', '1440'], true)) {
            throw new \InvalidArgumentException('OPS_DIAGNOSTIC_WINDOW_INVALID');
        }
        return (int)$candidate;
    }

    private function idempotencyKey(): string
    {
        $value = trim((string)$this->request->header('Idempotency-Key', ''));
        if ($value === '') {
            throw OpsConsoleException::invalid();
        }
        return $value;
    }

    private function ifMatchRevision(bool $allowZero): int
    {
        $value = trim((string)$this->request->header('If-Match', ''));
        if (preg_match('/^"rev-([0-9]+)"$/D', $value, $matches) !== 1) {
            throw OpsConsoleException::invalid();
        }
        $revision = (int)$matches[1];
        if (($allowZero && $revision < 0) || (!$allowZero && $revision < 1)) {
            throw OpsConsoleException::revisionConflict();
        }
        return $revision;
    }
}
