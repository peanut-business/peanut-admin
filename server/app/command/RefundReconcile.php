<?php
declare(strict_types=1);

namespace app\command;

use app\common\enum\RefundEnum;
use app\Modules\Official\Payment\Model\RechargeOrder;
use app\Modules\Official\Payment\Model\RefundLog;
use app\Modules\Official\Payment\Model\RefundRecord;
use app\common\service\payment\contract\RefundGatewayInterface;
use app\common\service\payment\PaymentServiceFactory;
use app\common\service\finance\FinanceTenantRepository;
use app\common\service\payment\PaymentScheduledTenantContext;
use app\common\service\payment\PaymentTenantDiagnostics;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Log;

/** 查询支付渠道并收敛充值退款的最终状态。 */
class RefundReconcile extends Command
{
    protected function configure()
    {
        $this->setName('refund:reconcile')->setDescription('收敛充值退款状态');
    }

    protected function execute(Input $input, Output $output)
    {
        $scope = PaymentScheduledTenantContext::require();
        $diagnostics = PaymentTenantDiagnostics::fromScope($scope);
        $records = FinanceTenantRepository::records($scope)
            ->where('order_type', RefundEnum::ORDER_TYPE_RECHARGE)
            ->where('refund_status', RefundEnum::REFUND_ING)
            ->order('id', 'asc')
            ->select();

        $checked = 0;
        $settled = 0;
        foreach ($records as $record) {
            /** @var RefundLog $log */
            $log = FinanceTenantRepository::logs($scope)->where('record_id', (int)$record->id)
                ->where('refund_status', RefundEnum::REFUND_ING)
                ->order('id', 'desc')
                ->findOrEmpty();
            /** @var RechargeOrder $order */
            $order = FinanceTenantRepository::orders($scope)->findOrEmpty((int)$record->order_id);
            if ($log->isEmpty() || $order->isEmpty()) {
                Log::warning('refund_reconcile_related_data_missing', $diagnostics + [
                    'record_id' => (int)$record->id,
                ]);
                continue;
            }

            $checked++;
            try {
                $channel = match ((int)$order->pay_way) {
                    RechargeOrder::PAY_WAY_WECHAT => 'wechat',
                    RechargeOrder::PAY_WAY_ALIPAY => 'alipay',
                    default => throw new \RuntimeException('支付方式异常'),
                };
                $result = PaymentServiceFactory::forTenant($scope, $channel)->refund($channel)->query(
                    $order->getData(),
                    (string)$log->sn
                );
            } catch (\Throwable $e) {
                Log::warning('refund_reconcile_gateway_query_failed', $diagnostics + [
                    'record_id' => (int)$record->id,
                    'exception' => $e::class,
                ]);
                continue;
            }

            $gatewayStatus = (string)($result['status'] ?? '');
            if ($gatewayStatus === RefundGatewayInterface::STATUS_PENDING) {
                continue;
            }
            if (!in_array($gatewayStatus, [
                RefundGatewayInterface::STATUS_SUCCESS,
                RefundGatewayInterface::STATUS_FAILED,
            ], true)) {
                Log::warning('refund_reconcile_gateway_status_unknown', $diagnostics + [
                    'record_id' => (int)$record->id,
                ]);
                continue;
            }

            Db::startTrans();
            try {
                /** @var RefundRecord $lockedRecord */
                $lockedRecord = FinanceTenantRepository::records($scope)->where('id', (int)$record->id)
                    ->lock(true)
                    ->findOrEmpty();
                /** @var RefundLog $lockedLog */
                $lockedLog = FinanceTenantRepository::logs($scope)->where('record_id', (int)$record->id)
                    ->order('id', 'desc')
                    ->lock(true)
                    ->findOrEmpty();
                /** @var RechargeOrder $lockedOrder */
                $lockedOrder = FinanceTenantRepository::orders($scope)->where('id', (int)$lockedRecord->order_id)
                    ->lock(true)
                    ->findOrEmpty();

                $isCurrent = !$lockedRecord->isEmpty()
                    && !$lockedLog->isEmpty()
                    && !$lockedOrder->isEmpty()
                    && (int)$lockedRecord->refund_status === RefundEnum::REFUND_ING
                    && (string)$lockedRecord->order_type === RefundEnum::ORDER_TYPE_RECHARGE
                    && (int)$lockedOrder->id === (int)$order->id
                    && (int)$lockedLog->id === (int)$log->id
                    && (int)$lockedLog->refund_status === RefundEnum::REFUND_ING;
                if (!$isCurrent) {
                    Db::rollback();
                    continue;
                }

                $finalStatus = $gatewayStatus === RefundGatewayInterface::STATUS_SUCCESS
                    ? RefundEnum::REFUND_SUCCESS
                    : RefundEnum::REFUND_ERROR;
                $message = self::encodeGatewayResult($result['receipt'] ?? []);
                $lockedLog->refund_status = $finalStatus;
                $lockedLog->refund_msg = $message;
                $lockedRecord->refund_status = $finalStatus;
                $lockedRecord->refund_msg = $message;

                if ($finalStatus === RefundEnum::REFUND_SUCCESS) {
                    $lockedOrder->refund_transaction_id = (string)($result['transaction_id'] ?? '');
                    $lockedOrder->save();
                }
                $lockedLog->save();
                $lockedRecord->save();
                Db::commit();
                $settled++;
            } catch (\Throwable $e) {
                Db::rollback();
                Log::warning('refund_reconcile_persist_failed', $diagnostics + [
                    'record_id' => (int)$record->id,
                    'exception' => $e::class,
                ]);
            }
        }

        $output->writeln(sprintf(
            '[refund:reconcile] checked=%d settled=%d',
            $checked,
            $settled
        ));
        return 0;
    }

    private static function encodeGatewayResult(mixed $result): string
    {
        if (is_string($result)) {
            return $result;
        }
        return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }
}
