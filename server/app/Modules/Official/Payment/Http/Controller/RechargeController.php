<?php
declare(strict_types=1);

namespace app\Modules\Official\Payment\Http\Controller;

use app\adminapi\controller\BaseAdminController;
use app\Modules\Official\Payment\Service\RechargeLogic;
use app\Modules\Official\Payment\Validation\RechargeValidate;
use app\common\service\JsonService;
use app\common\service\finance\FinanceTenantContext;
use PeanutAdmin\Kernel\Auth\TenantContext;

class RechargeController extends BaseAdminController
{
    public function lists()
    {
        $params = $this->request->get();
        $context = FinanceTenantContext::member($this->request);
        $this->validateForTenant($context, $params, 'lists');
        $result = RechargeLogic::lists($context, $params);
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
        $context = FinanceTenantContext::member($this->request);
        $this->validateForTenant($context, $params, 'refund');
        [$flag, $message] = RechargeLogic::refund(
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
        $context = FinanceTenantContext::member($this->request);
        $this->validateForTenant($context, $params, 'again');
        [$flag, $message] = RechargeLogic::refundAgain($context, $params, $this->adminId);
        return $flag ? $this->success($message) : $this->fail($message);
    }

    private function validateForTenant(TenantContext $context, array $data, string $scene): void
    {
        (new RechargeValidate())->forTenant($context)->scene($scene)->failException(true)->check($data);
    }
}
