<?php
declare(strict_types=1);

namespace app\common\controller;

use app\BaseController;
use app\common\http\PageResult;
use app\common\service\JsonService;
use think\response\Json;

class BaseLikeAdminController extends BaseController
{
    public array $notNeedLogin = [];

    protected function success(string $msg = 'success', mixed $data = []): Json
    {
        return JsonService::success($msg, $data);
    }

    protected function fail(string $msg = 'fail'): never
    {
        throw \app\common\http\ApiProblem::fromEnvelope($msg);
    }

    protected function data(mixed $data): Json
    {
        return JsonService::data($data);
    }

    protected function dataLists(PageResult $page): Json
    {
        return JsonService::dataLists($page);
    }

    public function isNotNeedLogin(): bool
    {
        if (empty($this->notNeedLogin)) return false;
        return in_array($this->request->action(), $this->notNeedLogin);
    }
}
