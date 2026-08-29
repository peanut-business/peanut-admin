<?php
declare(strict_types=1);

namespace app\adminapi\http;

use app\common\http\RequestTrace;

final class AdminRequest
{
    public static function requestId($request): string
    {
        return RequestTrace::id($request, 'admin');
    }

    private function __construct()
    {
    }
}
