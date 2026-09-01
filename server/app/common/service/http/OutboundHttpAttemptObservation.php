<?php
declare(strict_types=1);

namespace app\common\service\http;

use app\common\execution\ExecutionContextAccess;
use app\common\service\runtime\OperationalLog;
use GuzzleHttp\Exception\ConnectException;

/** Secret-free per-attempt observation shared by application-owned HTTP retries. */
final class OutboundHttpAttemptObservation
{
    public static function response(
        ExecutionContextAccess $contexts,
        string $method,
        string $url,
        int $attempt,
        int $startedAt,
        int $status,
    ): void
    {
        $category = match (true) {
            $status < 400 => 'success',
            $status < 500 => 'http_4xx',
            default => 'http_5xx',
        };
        self::write($contexts, $method, $url, $attempt, $startedAt, $category, $status);
    }

    public static function failure(
        ExecutionContextAccess $contexts,
        string $method,
        string $url,
        int $attempt,
        int $startedAt,
        \Throwable $exception,
    ): void
    {
        $category = $exception instanceof ConnectException
            && ((int)($exception->getHandlerContext()['errno'] ?? 0) === 28
                || str_contains(strtolower($exception->getMessage()), 'timed out'))
            ? 'timeout'
            : 'transport';
        self::write($contexts, $method, $url, $attempt, $startedAt, $category, null, $exception);
    }

    private static function write(
        ExecutionContextAccess $contexts,
        string $method,
        string $url,
        int $attempt,
        int $startedAt,
        string $category,
        ?int $status = null,
        ?\Throwable $exception = null,
    ): void {
        $attributes = [
            'method' => strtoupper($method),
            'host' => (string)(parse_url($url, PHP_URL_HOST) ?: 'unknown'),
            'attempt' => $attempt,
            'duration_ms' => max(0, intdiv(hrtime(true) - $startedAt, 1_000_000)),
            'outcome' => $category === 'success' ? 'success' : 'failed',
            'category' => $category,
        ];
        if ($status !== null) {
            $attributes['status'] = $status;
        }
        if ($exception !== null) {
            $attributes['exception'] = $exception::class;
        }
        OperationalLog::warning($contexts, 'outbound_http_attempt', $attributes);
    }

    private function __construct()
    {
    }
}
