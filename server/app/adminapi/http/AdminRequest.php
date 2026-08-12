<?php
declare(strict_types=1);

namespace app\adminapi\http;

final class AdminRequest
{
    public static function requestId($request): string
    {
        $candidate = trim((string)$request->header('X-Request-Id', ''));
        if ($candidate !== '' && preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,63}$/D', $candidate) === 1) {
            return $candidate;
        }

        return 'admin-' . bin2hex(random_bytes(16));
    }

    private function __construct()
    {
    }
}
