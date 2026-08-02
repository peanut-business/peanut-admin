<?php
declare(strict_types=1);

namespace app\adminapi\controller\finance;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\finance\RefundLogic;
use app\adminapi\validate\finance\RefundValidate;

/**
 * 退款控制器
 */
class RefundController extends BaseAdminController
{
    /** 退款统计（四个金额汇总） */
    public function stat()
    {
        return $this->data(RefundLogic::stat());
    }

    /** 退款记录列表（分页） */
    public function record()
    {
        $params = $this->request->get();
        $this->validate($params, RefundValidate::class . '.record');
        $result = RefundLogic::lists($params);
        return $result === false
            ? $this->fail(RefundLogic::getError())
            : $this->data($result);
    }

    /** 退款日志（某条退款记录的操作流水） */
    public function log()
    {
        $params = $this->request->get();
        $this->validate($params, RefundValidate::class . '.log');
        $recordId = (int)$params['record_id'];
        return $this->data(RefundLogic::refundLog($recordId));
    }
}
