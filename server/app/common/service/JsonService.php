<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\http\PageResult;
use think\response\Json;

class JsonService
{
    public static function success(string $msg = 'success', mixed $data = [], int $code = 20000): Json
    {
        return json(compact('code', 'msg', 'data'));
    }

    public static function data(mixed $data): Json
    {
        if ($data instanceof PageResult) {
            $data = $data->responseData();
        }
        return json(['code' => 20000, 'msg' => 'success', 'data' => $data]);
    }

    public static function dataLists(PageResult $page): Json
    {
        return self::data($page);
    }

}
