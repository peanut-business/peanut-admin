<?php
declare(strict_types=1);

namespace app\Modules\Official\Payment\Http\Controller;

use think\App;

use app\adminapi\controller\BaseAdminController;
use app\Modules\Official\Payment\Application\RefundApplicationService;
use app\Modules\Official\Payment\Validation\RefundValidate;
use app\common\service\finance\FinanceTenantContext;

/**
 * 退款控制器
 */
class RefundController extends BaseAdminController
{
    public function __construct(App $app, private readonly RefundApplicationService $refunds)
    {
        parent::__construct($app);
    }

    /** 退款统计（四个金额汇总） */
    public function stat()
    {
        return $this->data($this->refunds->stat(FinanceTenantContext::member()));
    }

    /** 退款记录列表（分页） */
    public function record()
    {
        $params = $this->request->get();
        $this->validate($params, RefundValidate::class . '.record');
        $result = $this->refunds->lists(FinanceTenantContext::member(), $params);
        return $result === false
            ? $this->fail($this->refunds->getError())
            : $this->data($result);
    }

    /** 退款日志（某条退款记录的操作流水） */
    public function log()
    {
        $params = $this->request->get();
        $this->validate($params, RefundValidate::class . '.log');
        $recordId = (int)$params['record_id'];
        return $this->data($this->refunds->refundLog(
            FinanceTenantContext::member(),
            $recordId
        ));
    }
}
