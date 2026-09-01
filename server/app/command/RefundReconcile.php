<?php
declare(strict_types=1);

namespace app\command;

use app\Modules\Official\Payment\Contracts\RefundReconciliationCommands;
use app\common\service\payment\PaymentScheduledTenantContext;
use app\common\service\payment\PaymentTenantDiagnostics;
use app\common\execution\ContextualCommand;
use app\common\execution\ExecutionContextAccess;
use app\common\execution\ExecutionContextStore;
use think\console\Input;
use think\console\Output;

/** 查询支付渠道并收敛充值退款的最终状态。 */
class RefundReconcile extends ContextualCommand
{
    public function __construct(
        ExecutionContextStore $contexts,
        ExecutionContextAccess $contextAccess,
        private readonly RefundReconciliationCommands $refunds,
    ) {
        parent::__construct($contexts, $contextAccess);
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
