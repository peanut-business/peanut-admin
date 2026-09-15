<?php
declare(strict_types=1);

namespace app\command;

use app\modules\official\payment\contracts\RefundReconciliationCommands;
use app\common\context\payment\PaymentScheduledTenantContext;
use app\common\infrastructure\payment\PaymentTenantDiagnostics;
use app\common\execution\ContextualCommand;
use app\common\execution\CurrentExecutionContext;
use app\common\execution\ExecutionContextStore;
use think\console\Input;
use think\console\Output;

/** 查询支付渠道并收敛充值退款的最终状态。 */
class RefundReconcile extends ContextualCommand
{
    public function __construct(
        ExecutionContextStore $contexts,
        CurrentExecutionContext $executionContext,
        private readonly RefundReconciliationCommands $refunds,
    ) {
        parent::__construct($contexts, $executionContext);
    }

    protected function configure()
    {
        $this->setName('refund:reconcile')->setDescription('收敛充值退款状态');
    }

    protected function handle(Input $input, Output $output): int
    {
        $scope = PaymentScheduledTenantContext::require();
        $diagnostics = PaymentTenantDiagnostics::fromScope($scope);
        $result = $this->refunds->reconcile($scope, $diagnostics);

        $output->writeln(sprintf(
            '[refund:reconcile] checked=%d settled=%d',
            $result['checked'],
            $result['settled']
        ));
        return 0;
    }

}
