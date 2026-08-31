<?php
declare(strict_types=1);

namespace app\common\http\middleware;

use app\common\service\installation\InstallationExecutionHost;

/** Keeps every business API closed while a guided fresh installation is incomplete. */
final class InstallationStateMiddleware
{
    private const INSTALLATION_PATHS = [
        'api/installation/status',
        'api/installation/execute',
    ];

    public function handle($request, \Closure $next)
    {
        if (trim((string)(getenv('PEANUT_INSTALLATION_MODE') ?: 'automatic')) !== 'guided'
            || in_array(trim((string)$request->pathinfo(), '/'), self::INSTALLATION_PATHS, true)) {
            return $next($request);
        }

        try {
            $status = (new InstallationExecutionHost(dirname(__DIR__, 4)))->status();
        } catch (\Throwable) {
            throw \app\common\http\ApiProblem::fromEnvelope(
                '系统安装状态不可用。',
                ['error_code' => 'INSTALL_STATUS_UNAVAILABLE'],
                50300,
            );
        }

        if (($status['state'] ?? null) !== 'installed') {
            throw \app\common\http\ApiProblem::fromEnvelope(
                '系统尚未完成安装。',
                ['error_code' => (string)($status['code'] ?? 'INSTALLATION_REQUIRED')],
                50300,
            );
        }

        return $next($request);
    }
}
