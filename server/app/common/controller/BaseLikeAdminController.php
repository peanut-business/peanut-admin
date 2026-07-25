<?php
declare(strict_types=1);

namespace app\common\controller;

use app\BaseController;
use app\common\service\JsonService;
use think\response\Json;

class BaseLikeAdminController extends BaseController
{
    public array $notNeedLogin = [];

    protected function success(string $msg = 'success', mixed $data = []): Json
    {
        return JsonService::success($msg, $data);
    }

    protected function fail(string $msg = 'fail'): Json
    {
        return JsonService::fail($msg);
    }

    protected function data(mixed $data): Json
    {
        return JsonService::data($data);
    }

    protected function dataLists(array $lists, int $count, int $pageNo = 1, int $pageSize = 15): Json
    {
        return JsonService::dataLists($lists, $count, $pageNo, $pageSize);
    }

    public function isNotNeedLogin(): bool
    {
        if (empty($this->notNeedLogin)) return false;
        return in_array($this->request->action(), $this->notNeedLogin);
    }
}
