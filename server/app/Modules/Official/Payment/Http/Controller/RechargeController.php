<?php
declare(strict_types=1);

namespace app\Modules\Official\Payment\Http\Controller;

use think\App;

use app\adminapi\controller\BaseAdminController;
use app\Modules\Official\Payment\Application\RechargeAdministrationService;
use app\Modules\Official\Payment\Validation\RechargeValidate;
use app\common\service\JsonService;
use app\common\service\finance\FinanceTenantContext;

class RechargeController extends BaseAdminController
{
    public function __construct(App $app, private readonly RechargeAdministrationService $recharges)
    {
        parent::__construct($app);
    }

    public function lists()
    {
        $params = $this->request->get();
        $context = FinanceTenantContext::member();
        $this->validate($params, RechargeValidate::class . '.lists');
        $result = $this->recharges->lists($context, $params);
        if ($result === false) {
            return $this->fail($this->recharges->getError());
        }
        if ((int)($params['export'] ?? 0) === 2) {
            return JsonService::success('', $result, 2);
        }
        return $this->data($result);
    }

    public function refund()
    {
        $params = $this->request->post();
        $context = FinanceTenantContext::member();
        $this->validate($params, RechargeValidate::class . '.refund');
        [$flag, $message] = $this->recharges->refund(
            $context,
            $params,
            $this->adminId,
            trim((string)$this->request->header('Idempotency-Key', '')),
        );
        return $flag ? $this->success($message) : $this->fail($message);
    }

    public function refundAgain()
    {
        $params = $this->request->post();
        $context = FinanceTenantContext::member();
        $this->validate($params, RechargeValidate::class . '.again');
        [$flag, $message] = $this->recharges->refundAgain($context, $params, $this->adminId);
        return $flag ? $this->success($message) : $this->fail($message);
    }

}
