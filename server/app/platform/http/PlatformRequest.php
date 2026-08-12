<?php
declare(strict_types=1);

namespace app\platform\http;

use PeanutAdmin\Kernel\Auth\PlatformRefreshCookie;

final class PlatformRequest
{
    public static function bearerToken($request): string
    {
        $authorization = trim((string)$request->header('Authorization', ''));
        if (preg_match('/^Bearer\s+(\S+)$/iD', $authorization, $matches) !== 1) {
            return '';
        }

        return $matches[1];
    }

    public static function refreshToken($request): string
    {
        return trim((string)$request->cookie(PlatformRefreshCookie::NAME, ''));
    }

    public static function requestId($request): string
    {
        $candidate = trim((string)$request->header('X-Request-Id', ''));
        if ($candidate !== '' && preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,63}$/D', $candidate) === 1) {
            return $candidate;
        }

        return 'platform-' . bin2hex(random_bytes(16));
    }

    private function __construct()
    {
    }
}
