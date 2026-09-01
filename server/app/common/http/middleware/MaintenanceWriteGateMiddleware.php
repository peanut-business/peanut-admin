<?php
declare(strict_types=1);

namespace app\common\http\middleware;

use app\common\http\RequestTrace;
use app\common\service\audit\AuditContractHost;
use PDO;
use PeanutAdmin\Kernel\Audit\AuditOutcome;
use think\facade\Db;

/** Fails closed for every HTTP mutation while an active maintenance window is in effect. */
final class MaintenanceWriteGateMiddleware
{
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle($request, \Closure $next)
    {
        if (!in_array(strtoupper((string)$request->method()), self::WRITE_METHODS, true)
            || $this->isMaintenanceControlRequest($request)
        ) {
            return $next($request);
        }

        $requestId = RequestTrace::id($request, 'maintenance');
        try {
            $pdo = Db::connect()->connect();
            if (!$pdo instanceof PDO) {
                throw new \RuntimeException('MAINTENANCE_GATE_DATABASE_UNAVAILABLE');
            }
            $window = $this->activeWindow($pdo);
            if ($window !== null) {
                AuditContractHost::fromPdo($pdo)->recordPlatform(
                    'platform.maintenance.write-blocked',
                    'maintenance.write',
                    $requestId,
                    null,
                    null,
                    [
                        'maintenance_key' => (string)$window['maintenance_key'],
                        'reason_key' => (string)$window['reason_key'],
                        'request_method' => strtoupper((string)$request->method()),
                        'request_path' => trim((string)$request->pathinfo(), '/'),
                    ],
                    AuditOutcome::Denied,
                    'MAINTENANCE_WRITE_BLOCKED',
                );
            }
        } catch (\Throwable) {
            throw \app\common\http\ApiProblem::fromEnvelope(
                '系统维护状态不可用，写入操作已拒绝。',
                ['error_code' => 'MAINTENANCE_GATE_UNAVAILABLE'],
                50300,
            )->withHeaders(['Cache-Control' => 'no-store', 'X-Request-Id' => $requestId]);
        }

        if ($window === null) {
            return $next($request);
        }

        throw \app\common\http\ApiProblem::fromEnvelope(
            '系统维护中，暂不支持写入操作。',
            ['error_code' => 'MAINTENANCE_WRITE_BLOCKED'],
            50300,
        )->withHeaders(['Cache-Control' => 'no-store', 'X-Request-Id' => $requestId]);
    }

    /** @return array{maintenance_key:string,reason_key:string}|null */
    private function activeWindow(PDO $pdo): ?array
    {
        $statement = $pdo->query(<<<'SQL'
SELECT maintenance_key, reason_key
FROM pa_ops_maintenance_window
WHERE state IN ('scheduled', 'active')
  AND starts_at <= UTC_TIMESTAMP(3)
  AND ends_at > UTC_TIMESTAMP(3)
ORDER BY id DESC
LIMIT 1
SQL);
        $window = $statement === false ? false : $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($window) ? $window : null;
    }

    private function isMaintenanceControlRequest($request): bool
    {
        $method = strtoupper((string)$request->method());
        $path = trim((string)$request->pathinfo(), '/');
        return ($method === 'PUT' && $path === 'platformapi/v1/ops/maintenance')
            || ($method === 'POST'
                && preg_match('#^platformapi/v1/ops/maintenance/maintenance_[a-f0-9]{32}/close$#D', $path) === 1);
    }

}
