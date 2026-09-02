<?php
declare(strict_types=1);

namespace app\Modules\Official\Payment\Infrastructure;

use app\common\execution\CurrentExecutionContext;
use app\common\persistence\TransactionalExecution;
use app\common\service\payment\contract\RefundGatewayInterface;
use app\common\service\payment\PaymentServiceFactory;
use app\common\service\runtime\OperationalLog;
use app\Modules\Official\Payment\Contracts\PaymentMethod;
use app\Modules\Official\Payment\Contracts\RefundReconciliationCommands;
use app\Modules\Official\Payment\Application\RefundEnum;
use app\Modules\Official\Payment\Infrastructure\Persistence\FinanceTenantRepository;

/** Queries providers and converges pending recharge refunds to a final state. */
final readonly class ThinkPhpRefundReconciliationCommands implements RefundReconciliationCommands
{
    public function __construct(
        private PaymentServiceFactory $payments,
        private TransactionalExecution $transactions,
        private CurrentExecutionContext $executionContext,
    ) {
    }

    public function reconcile(object $scope, array $diagnostics): array
    {
        $records = FinanceTenantRepository::records($scope)
            ->where('order_type', RefundEnum::ORDER_TYPE_RECHARGE)
            ->where('refund_status', RefundEnum::REFUND_ING)
            ->order('id', 'asc')
            ->select();

        $recordIds = [];
        $orderIds = [];
        foreach ($records as $record) {
            $recordIds[] = (int)$record->id;
            $orderIds[] = (int)$record->order_id;
        }
        $logsByRecord = [];
        if ($recordIds !== []) {
            $logs = FinanceTenantRepository::logs($scope)
                ->whereIn('record_id', $recordIds)
                ->where('refund_status', RefundEnum::REFUND_ING)
                ->order(['record_id' => 'asc', 'id' => 'desc'])
                ->select();
            foreach ($logs as $candidate) {
                $recordId = (int)$candidate->record_id;
                $logsByRecord[$recordId] ??= $candidate;
            }
        }
        $ordersById = [];
        if ($orderIds !== []) {
            foreach (FinanceTenantRepository::orders($scope)->whereIn('id', $orderIds)->select() as $candidate) {
                $ordersById[(int)$candidate->id] = $candidate;
            }
        }

        $checked = 0;
        $settled = 0;
        foreach ($records as $record) {
            $log = $logsByRecord[(int)$record->id] ?? null;
            $order = $ordersById[(int)$record->order_id] ?? null;
            if (!is_object($log) || !is_object($order)) {
                $this->warning('refund_reconcile_related_data_missing', $diagnostics, (int)$record->id);
                continue;
            }

            $checked++;
            if ($this->reconcileOne($scope, $record, $order, $log, $diagnostics)) {
                $settled++;
            }
        }

        return ['checked' => $checked, 'settled' => $settled];
    }

    /** @param array<string,mixed> $diagnostics */
    private function reconcileOne(object $scope, object $record, object $order, object $log, array $diagnostics): bool
    {
        try {
            $channel = match ((int)$order->pay_way) {
                PaymentMethod::WECHAT => 'wechat',
                PaymentMethod::ALIPAY => 'alipay',
                default => throw new \RuntimeException('支付方式异常'),
            };
            $result = $this->payments->forTenant($scope, $channel)->refund($channel)->query(
                $order->getData(),
                (string)$record->sn
            );
        } catch (\Throwable $e) {
            $this->warning('refund_reconcile_gateway_query_failed', $diagnostics, (int)$record->id, $e);
            return false;
        }

        $gatewayStatus = (string)($result['status'] ?? '');
        if ($gatewayStatus === RefundGatewayInterface::STATUS_PENDING) {
            return false;
        }
        if (!in_array($gatewayStatus, [
            RefundGatewayInterface::STATUS_SUCCESS,
            RefundGatewayInterface::STATUS_FAILED,
        ], true)) {
            $this->warning('refund_reconcile_gateway_status_unknown', $diagnostics, (int)$record->id);
            return false;
        }

        try {
            return $this->transactions->run(function () use (
                $scope,
                $record,
                $order,
                $log,
                $gatewayStatus,
                $result,
            ): bool {
                $lockedRecord = FinanceTenantRepository::records($scope)->where('id', (int)$record->id)
                    ->lock(true)
                    ->findOrEmpty();
                $lockedLog = FinanceTenantRepository::logs($scope)->where('record_id', (int)$record->id)
                    ->order('id', 'desc')
                    ->lock(true)
                    ->findOrEmpty();
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
                    return false;
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
                return true;
            });
        } catch (\Throwable $e) {
            $this->warning('refund_reconcile_persist_failed', $diagnostics, (int)$record->id, $e);
            return false;
        }
    }

    /** @param array<string,mixed> $diagnostics */
    private function warning(string $event, array $diagnostics, int $recordId, ?\Throwable $e = null): void
    {
        OperationalLog::warning($this->executionContext, $event, $diagnostics + array_filter([
            'record_id' => $recordId,
            'exception' => $e === null ? null : $e::class,
        ], static fn(mixed $value): bool => $value !== null));
    }

    private static function encodeGatewayResult(mixed $result): string
    {
        if (is_string($result)) {
            return $result;
        }
        return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }
}
