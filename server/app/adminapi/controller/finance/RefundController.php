<?php
declare(strict_types=1);

namespace app\adminapi\controller\finance;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\finance\RefundLogic;

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
        return $this->data(RefundLogic::lists($params));
    }

    /** 退款日志（某条退款记录的操作流水） */
    public function log()
    {
        $recordId = (int) $this->request->get('record_id', 0);
        return $this->data(RefundLogic::refundLog($recordId));
    }
}
