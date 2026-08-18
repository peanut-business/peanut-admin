<?php
declare(strict_types=1);

namespace app\api\controller;

use app\api\logic\RechargeLogic;
use app\common\service\payment\PaymentServiceFactory;
use app\common\service\payment\dto\CallbackRequest;
use app\common\service\external\ExternalTenantResolver;

/** 渠道匿名回调入口：仅验签后的标准事件可进入充值状态机。 */
class PaymentNotifyController extends BaseApiController
{
    public function wechat()
    {
        try {
            $request = new CallbackRequest(
                (string)$this->request->getContent(),
                (array)$this->request->header()
            );
            $resolution = ExternalTenantResolver::production()->verifiedModuleCallback(
                'core',
                ExternalTenantResolver::WECHAT_PAYMENT,
                (string)$this->request->route('binding'),
                'payment.settle',
                $this->operationId(),
                static fn(array $config) => (new PaymentServiceFactory($config))->callback('wechat')->parse($request),
            );
            $event = $resolution->verifiedValue;
            if ($event->status() === 'success' && !RechargeLogic::settle($resolution->context, $event)) {
                throw new \RuntimeException(RechargeLogic::getError());
            }
            return json(['code' => 'SUCCESS', 'message' => '成功']);
        } catch (\Throwable) {
            return json(['code' => 'FAIL', 'message' => '处理失败'], 500);
        }
    }

    public function alipay()
    {
        try {
            $request = new CallbackRequest('', [], $this->request->post());
            $resolution = ExternalTenantResolver::production()->verifiedModuleCallback(
                'core',
                ExternalTenantResolver::ALIPAY_PAYMENT,
                (string)$this->request->route('binding'),
                'payment.settle',
                $this->operationId(),
                static fn(array $config) => (new PaymentServiceFactory($config))->callback('alipay')->parse($request),
            );
            $event = $resolution->verifiedValue;
            if ($event->status() === 'success' && !RechargeLogic::settle($resolution->context, $event)) {
                throw new \RuntimeException(RechargeLogic::getError());
            }
            return response('success');
        } catch (\Throwable) {
            return response('failure', 500);
        }
    }

    private function operationId(): string
    {
        $requestId = trim((string)$this->request->header('X-Request-Id', ''));
        return $requestId !== '' ? $requestId : bin2hex(random_bytes(16));
    }
}
