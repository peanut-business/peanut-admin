<?php
declare(strict_types=1);

namespace app\api\controller;

use think\App;
use app\common\execution\CurrentExecutionContext;

use app\Modules\Official\Payment\Contracts\PaymentMethod;
use app\Modules\Official\Payment\Contracts\RechargeCommands;
use app\common\service\payment\dto\CallbackRequest;
use app\common\service\external\ExternalTenantResolver;
use app\common\execution\ExecutionContextStore;
use app\common\http\RequestTrace;
use app\common\service\module\ModuleExecutionBoundary;

/** 渠道匿名回调入口：仅验签后的标准事件可进入充值状态机。 */
class PaymentNotifyController extends BaseApiController
{
    public function __construct(
        App $app,
        CurrentExecutionContext $executionContext,
        private readonly RechargeCommands $recharges,
        private readonly ExecutionContextStore $executionContexts,
        private readonly ModuleExecutionBoundary $modules,
        private readonly ExternalTenantResolver $externalTenants,
    )
    {
        parent::__construct($app, $executionContext);
    }

    public function wechat()
    {
        $request = new CallbackRequest(
                (string)$this->request->getContent(),
                (array)$this->request->header()
            );
        $resolution = $this->externalTenants->verifiedCallback(
                ExternalTenantResolver::WECHAT_PAYMENT,
                (string)$this->request->route('binding'),
                'payment.settle',
                $this->operationId(),
                fn(array $config) => $this->recharges->parseCallback('wechat', $config, $request),
            );
        $event = $resolution->verifiedValue;
        $this->executionContexts->run(
                new \app\common\execution\SystemExecutionContext($resolution->context),
                function () use ($event, $resolution): void {
                    $this->modules->assertExternalCallback('official.payment');
                    if ($event->status() === 'success') {
                        $this->recharges->settleVerifiedCallback(
                        $resolution->binding->id,
                        $event,
                        PaymentMethod::WECHAT,
                        );
                    }
                },
            );
        return json(['code' => 'SUCCESS', 'message' => '成功']);
    }

    public function alipay()
    {
        $request = new CallbackRequest('', [], $this->request->post());
        $resolution = $this->externalTenants->verifiedCallback(
                ExternalTenantResolver::ALIPAY_PAYMENT,
                (string)$this->request->route('binding'),
                'payment.settle',
                $this->operationId(),
                fn(array $config) => $this->recharges->parseCallback('alipay', $config, $request),
            );
        $event = $resolution->verifiedValue;
        $this->executionContexts->run(
                new \app\common\execution\SystemExecutionContext($resolution->context),
                function () use ($event, $resolution): void {
                    $this->modules->assertExternalCallback('official.payment');
                    if ($event->status() === 'success') {
                        $this->recharges->settleVerifiedCallback(
                        $resolution->binding->id,
                        $event,
                        PaymentMethod::ALIPAY,
                        );
                    }
                },
            );
        return response('success');
    }

    private function operationId(): string
    {
        return RequestTrace::id($this->request, 'payment');
    }
}
