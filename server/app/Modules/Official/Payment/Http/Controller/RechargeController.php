<?php
declare(strict_types=1);

namespace app\Modules\Official\Payment\Http\Controller;

use think\App;
use app\common\execution\CurrentExecutionContext;

use app\adminapi\controller\BaseAdminController;
use app\Modules\Official\Payment\Application\RechargeAdministrationService;
use app\Modules\Official\Payment\Validation\RechargeValidate;
use app\common\service\JsonService;

class RechargeController extends BaseAdminController
{
    public function __construct(App $app, CurrentExecutionContext $executionContext, private readonly RechargeAdministrationService $recharges)
    {
        parent::__construct($app, $executionContext);
    }

    public function lists()
    {
        $params = $this->request->get();
        $context = $this->tenantAdminContext();
        $this->validate($params, RechargeValidate::class . '.lists');
        $result = $this->recharges->lists($context, $params);
        if ((int)($params['export'] ?? 0) === 2) {
            return JsonService::success('', $result, 2);
        }
        return $this->data($result);
    }

    public function refund()
    {
        $params = $this->request->post();
        $context = $this->tenantAdminContext();
        $this->validate($params, RechargeValidate::class . '.refund');
        $message = $this->recharges->refund(
            $context,
            $params,
            $this->adminId,
            trim((string)$this->request->header('Idempotency-Key', '')),
        );
        return $this->success($message);
    }

    public function refundAgain()
    {
        $params = $this->request->post();
        $context = $this->tenantAdminContext();
        $this->validate($params, RechargeValidate::class . '.again');
        $message = $this->recharges->refundAgain($context, $params, $this->adminId);
        return $this->success($message);
    }

}
