<?php
declare(strict_types=1);

namespace app\adminapi\controller\finance;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\finance\RechargeLogic;
use app\adminapi\validate\finance\RechargeValidate;
use app\common\service\JsonService;

class RechargeController extends BaseAdminController
{
    public function lists()
    {
        $params = $this->request->get();
        $this->validate($params, RechargeValidate::class . '.lists');
        $result = RechargeLogic::lists($params);
        if ($result === false) {
            return $this->fail(RechargeLogic::getError());
        }
        if ((int)($params['export'] ?? 0) === 2) {
            return JsonService::success('', $result, 2);
        }
        return $this->data($result);
    }

    public function refund()
    {
        $params = $this->request->post();
        $this->validate($params, RechargeValidate::class . '.refund');
        [$flag, $message] = RechargeLogic::refund($params, $this->adminId);
        return $flag ? $this->success($message) : $this->fail($message);
    }

    public function refundAgain()
    {
        $params = $this->request->post();
        $this->validate($params, RechargeValidate::class . '.again');
        [$flag, $message] = RechargeLogic::refundAgain($params, $this->adminId);
        return $flag ? $this->success($message) : $this->fail($message);
    }
}
