<?php
declare(strict_types=1);

namespace app\Modules\Official\Payment\Application;

use app\Modules\Official\Member\Contracts\Dto\MemberBalanceMutation;
use app\Modules\Official\Member\Contracts\MemberContracts;
use DateTimeImmutable;
use app\common\enum\AccountLogEnum;
use app\common\enum\RefundEnum;
use app\common\contract\idempotency\IdempotentCommandExecutor;
use app\common\contract\idempotency\IdempotencyCommand;
use app\common\contract\idempotency\IdempotencyReceipt;
use app\common\contract\idempotency\IdempotencyResult;
use app\common\http\PageResult;
use app\common\application\ApplicationService;
use app\Modules\Official\Payment\Model\RechargeOrder;
use app\Modules\Official\Payment\Model\RefundLog;
use app\Modules\Official\Payment\Model\RefundRecord;
use app\common\service\FileService;
use app\common\service\MemberBalanceService;
use app\common\service\finance\FinanceTenantContext;
use app\common\service\finance\FinanceTenantRepository;
use app\common\service\payment\PaymentRetryLock;
use app\common\service\payment\contract\RefundGatewayInterface;
use app\common\service\payment\PaymentServiceFactory;
use app\common\service\XlsxExportService;
use app\common\support\ExportPageInfo;
use app\common\support\PaginationInput;
use PeanutAdmin\Kernel\Persistence\TransactionManager;

/** 充值记录查询、部分退款和失败重试。 */
class RechargeAdministrationService extends ApplicationService
{
    private const EXPORT_MAX_ROWS = 25000;
    private const EXPORT_DEFAULT_NAME = '充值记录';

    public function __construct(
        private readonly XlsxExportService $xlsxExport,
        private readonly IdempotentCommandExecutor $refundIdempotency,
        private readonly TransactionManager $transactions,
        private readonly PaymentRetryLock $retryLocks,
    ) {}

    /**
     * @return PageResult|array|false
     */
    public function lists(object $context, array $params): PageResult|array|false
    {
        try {
            $count = self::buildListQuery($context, $params)->count();
            $pageSize = max(1, min(
                self::EXPORT_MAX_ROWS,
                (int)($params['page_size'] ?? $params['limit'] ?? 25)
            ));

            if ((int)($params['export'] ?? 0) === 1) {
                return self::exportInfo($count, $pageSize);
            }
            if ((int)($params['export'] ?? 0) === 2) {
                return $this->export($context, $params, $count, $pageSize);
            }

            $pageType = (int)($params['page_type'] ?? 1);
            $pageNo = $pageType === 0
                ? 1
                : PaginationInput::from($params)->page;
            if ($pageType === 0) {
                $pageSize = self::EXPORT_MAX_ROWS;
            }

            $query = self::buildListQuery($context, $params)->order('ro.id', 'desc');
            $pageResult = $pageType === 0
                ? PageResult::fromPaginator($query->paginate([
                    'list_rows' => $pageSize,
                    'page' => $pageNo,
                    'var_page' => 'page_no',
                ]), $pageNo)
                : PaginationInput::from($params)->result($query);
            $rows = self::withRefundedAmounts($context, array_map(
                static fn($item): array => $item instanceof \think\Model ? $item->toArray() : (array) $item,
                $pageResult->items,
            ));

            return new PageResult(
                self::formatRows($rows),
                $pageResult->total,
                $pageResult->page,
                $pageResult->pageSize,
                ['extend' => []],
            );
        } catch (\Throwable $e) {
            $this->setError($e->getMessage());
            return false;
        }
    }

    /**
     * 创建一笔部分或全额退款。资格检查在订单行锁内执行，防止并发超额退款。
     * @return array{0:bool,1:string}
     */
    public function refund(
        object $context,
        array $params,
        int $adminId,
        string $idempotencyKey,
    ): array
    {
        try {
            $prepared = $this->transactions->run(function () use ($context, $params, $adminId, $idempotencyKey): array {
                $idempotency = $this->refundIdempotency;
                /** @var RechargeOrder $order */
                $order = FinanceTenantRepository::orders($context)->lock(true)->findOrEmpty((int)$params['recharge_id']);
                self::assertRefundableOrder($order);

                $requestedAmount = $params['refund_amount'] ?? null;
                $requestedCents = $requestedAmount === null || $requestedAmount === ''
                    ? null
                    : MemberBalanceService::moneyToCents((string)$requestedAmount);
                $lease = $idempotency->begin(IdempotencyCommand::tenant(
                    $context,
                    'recharge.refund.create',
                    $idempotencyKey,
                    self::refundRequestHash((int)$order->id, $requestedCents),
                    new DateTimeImmutable('+24 hours'),
                ));
                if (!$lease->isExecutionOwner()) {
                    return compact('idempotency', 'lease') + ['replay' => true];
                }

                $amountCents = self::requestedRefundAmountCents($context, $order, $requestedCents);
                $amount = $amountCents / 100;
                $refundSn = RefundRecord::generateSn();

                $order->refund_status = RechargeOrder::REFUND_STATUS_STARTED;
                $order->save();

                MemberContracts::balanceCommands()->applyInTransaction(
                    $context,
                    new MemberBalanceMutation(
                        (int)$order->user_id,
                        AccountLogEnum::USER_MONEY_DEC_RECHARGE_REFUND,
                        AccountLogEnum::DEC,
                        $amountCents,
                        $refundSn,
                        '充值订单退款',
                        [],
                        $adminId,
                        -$amountCents,
                        '退款失败:用户余额已不足退款金额',
                    ),
                );

                /** @var RefundRecord $record */
                $record = FinanceTenantRepository::createRecord($context, [
                    'sn' => $refundSn,
                    'user_id' => (int)$order->user_id,
                    'order_id' => (int)$order->id,
                    'order_sn' => (string)$order->sn,
                    'order_type' => RefundEnum::ORDER_TYPE_RECHARGE,
                    'order_amount' => $amount,
                    'refund_amount' => $amount,
                    'transaction_id' => (string)($order->transaction_id ?? ''),
                    'refund_way' => RefundEnum::getRefundWayByPayWay((int)$order->pay_way),
                    'refund_type' => RefundEnum::TYPE_ADMIN,
                    'refund_status' => RefundEnum::REFUND_ING,
                    'refund_msg' => '',
                ]);
                $log = self::createRefundLog($context, $order, $record, $amount, $adminId);

                return compact('idempotency', 'lease', 'order', 'record', 'log') + ['replay' => false];
            });
        } catch (\Throwable $e) {
            $this->setError($e->getMessage());
            return [false, $e->getMessage()];
        }

        if ($prepared['replay']) {
            return self::replayIdempotentRefund($prepared['lease']);
        }
        $idempotency = $prepared['idempotency'];
        $lease = $prepared['lease'];
        $order = $prepared['order'];
        $record = $prepared['record'];
        $log = $prepared['log'];

        // 渠道调用必须发生在本地原子业务事务提交后，避免渠道已受理而本地整体回滚。
        return $this->requestGatewayRefund($context, $order, $record, $log, $idempotency, $lease);
    }

    /**
     * 失败退款重试：复用 record，只新建 log，不再调整任何账户金额。
     * @return array{0:bool,1:string}
     */
    public function refundAgain(object $context, array $params, int $adminId): array
    {
        $recordId = (int)$params['record_id'];
        $retryLock = $this->retryLocks->name($context, $recordId);
        if (!$this->retryLocks->acquire($retryLock)) {
            $message = '退款正在处理中，请勿重复操作';
            $this->setError($message);
            return [false, $message];
        }

        try {
            try {
                [$order, $record, $log] = $this->transactions->run(function () use ($context, $recordId, $adminId): array {
                    /** @var RefundRecord $record */
                    $record = FinanceTenantRepository::records($context)->lock(true)->findOrEmpty($recordId);
                    if ($record->isEmpty()) {
                        throw new \RuntimeException('退款记录不存在');
                    }
                    if ((int)$record->refund_status === RefundEnum::REFUND_SUCCESS) {
                        throw new \RuntimeException('该退款记录已退款成功');
                    }
                    if ((int)$record->refund_status !== RefundEnum::REFUND_ERROR) {
                        throw new \RuntimeException('退款正在处理中，请勿重复操作');
                    }

                    /** @var RechargeOrder $order */
                    $order = FinanceTenantRepository::orders($context)->lock(true)->findOrEmpty((int)$record->order_id);
                    if ($order->isEmpty()) {
                        throw new \RuntimeException('充值订单不存在');
                    }

                    $record->refund_status = RefundEnum::REFUND_ING;
                    $record->refund_msg = '';
                    $record->save();

                    $log = self::createRefundLog(
                        $context,
                        $order,
                        $record,
                        (float)$record->refund_amount,
                        $adminId
                    );
                    return [$order, $record, $log];
                });
            } catch (\Throwable $e) {
                $this->setError($e->getMessage());
                return [false, $e->getMessage()];
            }

            // 重试同样先提交 ERROR -> ING 和本次日志，再在事务外请求渠道。
            return $this->requestGatewayRefund($context, $order, $record, $log);
        } finally {
            $this->retryLocks->release($retryLock);
        }
    }

    private static function assertRefundableOrder(RechargeOrder $order): void
    {
        if ($order->isEmpty()) {
            throw new \RuntimeException('充值订单不存在');
        }
        if ((int)$order->pay_status !== RechargeOrder::PAY_STATUS_PAID) {
            throw new \RuntimeException('当前订单不可退款');
        }
    }

    private static function requestedRefundAmountCents(object $context, RechargeOrder $order, mixed $requested): int
    {
        $orderCents = MemberBalanceService::moneyToCents((string)$order->order_amount);
        $refundedCents = MemberBalanceService::moneyToCents((string)(FinanceTenantRepository::records($context)
            ->where('order_type', RefundEnum::ORDER_TYPE_RECHARGE)
            ->where('order_id', (int)$order->id)
            ->sum('refund_amount') ?? 0));
        $remainingCents = $orderCents - $refundedCents;
        if ($remainingCents <= 0) {
            throw new \RuntimeException('充值订单可退款金额已用尽');
        }

        $amountCents = $requested === null ? $remainingCents : (int)$requested;
        if ($amountCents <= 0 || $amountCents > $remainingCents) {
            throw new \RuntimeException('退款金额超过当前可退款金额');
        }

        $member = FinanceTenantRepository::orders($context, 'ro')
            ->join('member m', 'm.tenant_id = ro.tenant_id AND m.id = ro.user_id')
            ->where('ro.id', (int)$order->id)
            ->field('m.user_money')
            ->findOrEmpty();
        if ($member->isEmpty() || MemberBalanceService::moneyToCents((string)$member->user_money) < $amountCents) {
            throw new \RuntimeException('退款失败:用户余额已不足退款金额');
        }
        return $amountCents;
    }

    private static function refundRequestHash(int $orderId, ?int $amountCents): string
    {
        return hash('sha256', json_encode([
            'recharge_id' => $orderId,
            'refund_amount_cents' => $amountCents,
        ], JSON_THROW_ON_ERROR));
    }

    /** @return array{0:bool,1:string} */
    private static function replayIdempotentRefund(IdempotencyResult $idempotency): array
    {
        if ($idempotency->isReplayable()) {
            $body = $idempotency->responseBody();
            return [(bool)($body['success'] ?? false), (string)($body['message'] ?? '操作成功')];
        }
        throw new \RuntimeException('退款请求仍在处理中，请稍后查询退款记录');
    }

    /** @param array{0:bool,1:string} $result */
    private static function finishRefundIdempotency(
        ?IdempotentCommandExecutor $idempotency,
        ?IdempotencyResult $lease,
        array $result,
    ): void
    {
        if ($idempotency === null || $lease === null || !$lease->isExecutionOwner()) {
            return;
        }
        $body = ['success' => $result[0], 'message' => $result[1]];
        if ($result[0]) {
            $idempotency->complete($lease, new IdempotencyReceipt(200, $body));
            return;
        }
        $idempotency->fail($lease, new IdempotencyReceipt(400, $body));
    }

    private static function createRefundLog(
        object $context,
        RechargeOrder $order,
        RefundRecord $record,
        float $amount,
        int $adminId
    ): RefundLog {
        return FinanceTenantRepository::createLog($context, [
            'sn' => RefundLog::generateSn(),
            'record_id' => (int)$record->id,
            'user_id' => (int)$order->user_id,
            'handle_id' => $adminId,
            'order_amount' => (float)$order->order_amount,
            'refund_amount' => round($amount, 2),
            'refund_status' => RefundEnum::REFUND_ING,
            'refund_msg' => '',
        ]);
    }

    /** @return array{0:bool,1:string} */
    private function requestGatewayRefund(
        object $context,
        RechargeOrder $order,
        RefundRecord $record,
        RefundLog $log,
        ?IdempotentCommandExecutor $idempotency = null,
        ?IdempotencyResult $lease = null,
    ): array {
        $result = null;
        $gatewayError = null;
        try {
            $channel = match ((int)$order->pay_way) {
                RechargeOrder::PAY_WAY_WECHAT => 'wechat',
                RechargeOrder::PAY_WAY_ALIPAY => 'alipay',
                default => throw new \RuntimeException('支付方式异常'),
            };
            $result = PaymentServiceFactory::forTenant($context, $channel)->refund($channel)->refund(
                $order->getData(),
                (string)$record->sn,
                MemberBalanceService::moneyToCents((string)$record->refund_amount)
            );
        } catch (\Throwable $e) {
            $message = $e->getMessage() !== '' ? $e->getMessage() : '支付渠道退款失败';
            if ((int)$e->getCode() === RefundGatewayInterface::ERROR_RESULT_UNKNOWN) {
                // 请求可能已被渠道受理，保持退款中交由 refund:reconcile 查询收敛。
                $result = [
                    'status' => RefundGatewayInterface::STATUS_PENDING,
                    'transaction_id' => '',
                    'receipt' => ['message' => $message],
                ];
            } else {
                $gatewayError = $message;
            }
        }

        $businessResult = $gatewayError === null
            ? [true, '操作成功']
            : [false, $gatewayError];

        // 渠道请求完成后使用新的短事务锁定本次记录和日志，原子落下业务结果和幂等回执。
        try {
            $this->transactions->run(function () use (
                $context,
                $record,
                $log,
                $order,
                $gatewayError,
                $result,
                $idempotency,
                $lease,
                $businessResult,
            ): void {
                /** @var RefundRecord $lockedRecord */
                $lockedRecord = FinanceTenantRepository::records($context)->lock(true)->findOrEmpty((int)$record->id);
                /** @var RefundLog $lockedLog */
                $lockedLog = FinanceTenantRepository::logs($context)->lock(true)->findOrEmpty((int)$log->id);
                /** @var RechargeOrder $lockedOrder */
                $lockedOrder = FinanceTenantRepository::orders($context)->lock(true)->findOrEmpty((int)$order->id);
                if ($lockedRecord->isEmpty() || $lockedLog->isEmpty() || $lockedOrder->isEmpty()) {
                    throw new \RuntimeException('退款结果关联数据不存在');
                }

                if ($gatewayError !== null) {
                    $lockedLog->refund_status = RefundEnum::REFUND_ERROR;
                    $lockedRecord->refund_status = RefundEnum::REFUND_ERROR;
                    $message = $gatewayError;
                } else {
                    $message = self::encodeGatewayResult($result['receipt'] ?? []);
                    if (($result['status'] ?? '') === RefundGatewayInterface::STATUS_SUCCESS) {
                        $lockedLog->refund_status = RefundEnum::REFUND_SUCCESS;
                        $lockedRecord->refund_status = RefundEnum::REFUND_SUCCESS;
                        $lockedOrder->refund_transaction_id = (string)($result['transaction_id'] ?? '');
                        $lockedOrder->save();
                    }
                }

                $lockedLog->refund_msg = $message;
                $lockedRecord->refund_msg = $message;
                $lockedLog->save();
                $lockedRecord->save();
                self::finishRefundIdempotency($idempotency, $lease, $businessResult);
            });
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            $this->setError($message);
            return [false, $message];
        }

        if (!$businessResult[0]) {
            $this->setError($businessResult[1]);
        }
        return $businessResult;
    }

    private static function encodeGatewayResult(mixed $result): string
    {
        if (is_string($result)) {
            return $result;
        }
        return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    private static function buildListQuery(object $context, array $params)
    {
        FinanceTenantContext::tenantId($context);
        $query = FinanceTenantRepository::orders($context, 'ro')
            ->join('member u', 'u.tenant_id = ro.tenant_id AND u.id = ro.user_id')
            ->field(
                'ro.id,ro.sn,ro.order_amount,ro.pay_way,ro.pay_time,'
                . 'ro.pay_status,ro.create_time,ro.refund_status,'
                . 'u.avatar,u.nickname,u.account'
            );

        if (!empty($params['sn'])) {
            $query->where('ro.sn', trim((string)$params['sn']));
        }
        $userInfo = trim((string)($params['user_info'] ?? $params['keyword'] ?? ''));
        if ($userInfo !== '') {
            $query->where(
                'u.sn|u.nickname|u.mobile|u.account',
                'like',
                '%' . $userInfo . '%'
            );
        }
        if (isset($params['pay_way']) && $params['pay_way'] !== '') {
            $query->where('ro.pay_way', (int)$params['pay_way']);
        }
        $payStatus = $params['pay_status'] ?? $params['status'] ?? '';
        if ($payStatus !== '') {
            $query->where('ro.pay_status', (int)$payStatus);
        }
        if (!empty($params['start_time']) && !empty($params['end_time'])) {
            $query->whereBetween('ro.create_time', [
                strtotime((string)$params['start_time']),
                strtotime((string)$params['end_time']),
            ]);
        }

        return $query;
    }

    private static function withRefundedAmounts(object $context, array $rows): array
    {
        $orderIds = array_values(array_unique(array_map('intval', array_column($rows, 'id'))));
        $amounts = $orderIds === [] ? [] : FinanceTenantRepository::records($context)
            ->where('order_type', RefundEnum::ORDER_TYPE_RECHARGE)
            ->whereIn('order_id', $orderIds)
            ->group('order_id')
            ->column('SUM(refund_amount)', 'order_id');
        foreach ($rows as &$row) {
            $row['refunded_amount'] = $amounts[(int)$row['id']] ?? 0;
        }
        unset($row);
        return $rows;
    }

    private static function formatRows(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['id'] = (int)$row['id'];
            $row['pay_way'] = (int)$row['pay_way'];
            $row['pay_status'] = (int)$row['pay_status'];
            $row['refund_status'] = (int)$row['refund_status'];
            $orderCents = MemberBalanceService::moneyToCents((string)$row['order_amount']);
            $refundedCents = MemberBalanceService::moneyToCents((string)($row['refunded_amount'] ?? 0));
            $row['refunded_amount'] = MemberBalanceService::centsToMoney($refundedCents);
            $row['refundable_amount'] = MemberBalanceService::centsToMoney(max(0, $orderCents - $refundedCents));
            $row['pay_way_text'] = [
                RechargeOrder::PAY_WAY_BALANCE => '余额支付',
                RechargeOrder::PAY_WAY_WECHAT => '微信支付',
                RechargeOrder::PAY_WAY_ALIPAY => '支付宝支付',
            ][$row['pay_way']] ?? '';
            $row['pay_status_text'] = [
                RechargeOrder::PAY_STATUS_UNPAID => '未支付',
                RechargeOrder::PAY_STATUS_PAID => '已支付',
            ][$row['pay_status']] ?? '';
            $row['avatar'] = FileService::getFileUrl((string)($row['avatar'] ?? ''));
            $row['pay_time'] = self::formatTime($row['pay_time'] ?? 0);
            $row['create_time'] = self::formatTime($row['create_time'] ?? 0);
        }
        unset($row);
        return $rows;
    }

    private static function formatTime(mixed $value): string
    {
        if (empty($value)) {
            return '';
        }
        return is_numeric($value) ? date('Y-m-d H:i:s', (int)$value) : (string)$value;
    }

    private static function exportInfo(int $count, int $pageSize): array
    {
        return ExportPageInfo::from(
            $count,
            $pageSize,
            self::EXPORT_MAX_ROWS,
            self::EXPORT_DEFAULT_NAME,
        )->toArray();
    }

    private function export(object $context, array $params, int $count, int $pageSize): array
    {
        if ($count === 0) {
            throw new \RuntimeException('没有数据,无法导出');
        }

        $pageType = (int)($params['page_type'] ?? 0);
        if ($pageType === 1) {
            $pageStart = max(1, (int)($params['page_start'] ?? 1));
            $pageEnd = max($pageStart, (int)($params['page_end'] ?? $pageStart));
            $offset = ($pageStart - 1) * $pageSize;
            $limit = ($pageEnd - $pageStart + 1) * $pageSize;
            if ($limit > self::EXPORT_MAX_ROWS) {
                throw new \RuntimeException(
                    '已超出系统限制数量，请分页查询或导出，当前最多记录数为：25000'
                );
            }
            if ($offset >= $count) {
                throw new \RuntimeException(
                    '第' . $pageStart . '页到第' . $pageEnd . '页没有数据，无法导出'
                );
            }
        } else {
            $offset = 0;
            $limit = min($count, self::EXPORT_MAX_ROWS);
        }

        $rows = self::withRefundedAmounts($context, self::buildListQuery($context, $params)
            ->order('ro.id', 'desc')
            ->limit($offset, $limit)
            ->select()
            ->toArray());
        $file = $this->xlsxExport->create(
            (string)($params['file_name'] ?? self::EXPORT_DEFAULT_NAME),
            ['充值单号', '用户昵称', '充值金额', '支付方式', '支付状态', '支付时间', '下单时间'],
            array_map(static fn(array $row): array => [
                $row['sn'],
                $row['nickname'],
                (float)$row['order_amount'],
                $row['pay_way_text'],
                $row['pay_status_text'],
                $row['pay_time'],
                $row['create_time'],
            ], self::formatRows($rows))
        );

        return [
            'url' => $file['url'],
            'file_name' => $file['original_name'],
        ];
    }

}
