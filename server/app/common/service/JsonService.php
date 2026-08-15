<?php
declare(strict_types=1);

namespace app\common\service;

use think\Response;
use think\response\Json;
use think\exception\HttpResponseException;
use think\facade\Log;

class JsonService
{
    public static function success(string $msg = 'success', mixed $data = [], int $code = 20000): Json
    {
        return json(compact('code', 'msg', 'data'));
    }

    public static function fail(string $msg = 'fail', mixed $data = null, int $code = 40000): Json
    {
        self::logDevelopmentFailure($code, $msg);
        return json(compact('code', 'msg', 'data'));
    }

    public static function data(mixed $data): Json
    {
        return json(['code' => 20000, 'msg' => 'success', 'data' => $data]);
    }

    public static function dataLists(array $lists, int $count, int $pageNo = 1, int $pageSize = 15): Json
    {
        return json([
            'code' => 20000,
            'msg'  => 'success',
            'data' => compact('lists', 'count', 'pageNo', 'pageSize'),
        ]);
    }

    public static function throw(string $msg = 'fail', int $code = 40000): never
    {
        self::logDevelopmentFailure($code, $msg);
        $response = Response::create(['code' => $code, 'msg' => $msg, 'data' => null], 'json');
        throw new HttpResponseException($response);
    }

    private static function logDevelopmentFailure(int $code, string $message): void
    {
        if (!app()->isDebug()) {
            return;
        }
        try {
            $request = request();
            Log::warning('api_failure ' . json_encode([
                'method' => $request->method(),
                'path' => '/' . ltrim($request->pathinfo(), '/'),
                'code' => $code,
                'message' => $message,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } catch (\Throwable) {
            // Diagnostics must never change the API response path.
        }
    }
}
