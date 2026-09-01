<?php
declare(strict_types=1);

namespace app\api\controller;

use think\App;
use app\common\execution\CurrentExecutionContext;

use app\api\application\RechargeApplicationService;
use app\Modules\Official\Payment\Model\PaymentScene;
use app\common\service\payment\PaymentServiceFactory;
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
        private readonly RechargeApplicationService $recharges,
        private readonly ExecutionContextStore $executionContexts,
        private readonly ModuleExecutionBoundary $modules,
    )
    {
        parent::__construct($app, $executionContext);
    }

    public function wechat()
    {
        try {
            $request = new CallbackRequest(
                (string)$this->request->getContent(),
                (array)$this->request->header()
            );
            $resolution = ExternalTenantResolver::production()->verifiedCallback(
                ExternalTenantResolver::WECHAT_PAYMENT,
                (string)$this->request->route('binding'),
                'payment.settle',
                $this->operationId(),
                static fn(array $config) => (new PaymentServiceFactory($config))->callback('wechat')->parse($request),
            );
            $event = $resolution->verifiedValue;
            $this->executionContexts->run(
                new \app\common\execution\SystemExecutionContext($resolution->context),
                function () use ($event, $resolution): void {
                    $this->modules->assertExternalCallback('official.payment');
                    if ($event->status() === 'success' && !$this->recharges->settleVerifiedCallback(
                        $resolution->binding->id,
                        $event,
                        PaymentScene::PAY_WAY_WECHAT,
                    )) {
                        throw new \RuntimeException($this->recharges->getError());
                    }
                },
            );
            return json(['code' => 'SUCCESS', 'message' => '成功']);
        } catch (\Throwable) {
            return json(['code' => 'FAIL', 'message' => '处理失败'], 500);
        }
    }

    public function alipay()
    {
        try {
            $request = new CallbackRequest('', [], $this->request->post());
            $resolution = ExternalTenantResolver::production()->verifiedCallback(
                ExternalTenantResolver::ALIPAY_PAYMENT,
                (string)$this->request->route('binding'),
                'payment.settle',
                $this->operationId(),
                static fn(array $config) => (new PaymentServiceFactory($config))->callback('alipay')->parse($request),
            );
            $event = $resolution->verifiedValue;
            $this->executionContexts->run(
                new \app\common\execution\SystemExecutionContext($resolution->context),
                function () use ($event, $resolution): void {
                    $this->modules->assertExternalCallback('official.payment');
                    if ($event->status() === 'success' && !$this->recharges->settleVerifiedCallback(
                        $resolution->binding->id,
                        $event,
                        PaymentScene::PAY_WAY_ALIPAY,
                    )) {
                        throw new \RuntimeException($this->recharges->getError());
                    }
                },
            );
            return response('success');
        } catch (\Throwable) {
            return response('failure', 500);
        }
    }

    private function operationId(): string
    {
        return RequestTrace::id($this->request, 'payment');
    }
}
