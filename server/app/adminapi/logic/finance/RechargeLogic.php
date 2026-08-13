<?php
declare(strict_types=1);

namespace app\adminapi\logic\finance;

use app\common\enum\AccountLogEnum;
use app\common\enum\RefundEnum;
use app\common\logic\BaseLogic;
use app\common\model\finance\RechargeOrder;
use app\common\model\refund\RefundLog;
use app\common\model\refund\RefundRecord;
use app\common\service\FileService;
use app\common\service\MemberBalanceService;
use app\common\service\finance\FinanceTenantContext;
use app\common\service\finance\FinanceTenantRepository;
use app\common\service\tenant\TenantLockNamespace;
use app\common\service\tenant\TenantScope;
use app\common\service\payment\contract\RefundGatewayInterface;
use app\common\service\payment\PaymentServiceFactory;
use app\common\service\XlsxExportService;
use think\facade\Db;
use PeanutAdmin\Kernel\Auth\TenantContext;

/** 充值记录查询、首次退款和失败重试。 */
class RechargeLogic extends BaseLogic
{
    private const EXPORT_MAX_ROWS = 25000;
    private const EXPORT_DEFAULT_NAME = '充值记录';

    /**
     * @return array{lists:array,count:int,page_no:int,page_size:int,extend:array}|array|false
     */
    public static function lists(TenantContext $context, array $params): array|false
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
                return self::export($context, $params, $count, $pageSize);
            }

            $pageType = (int)($params['page_type'] ?? 1);
            $pageNo = $pageType === 0
                ? 1
                : max(1, (int)($params['page_no'] ?? $params['page'] ?? 1));
            if ($pageType === 0) {
                $pageSize = self::EXPORT_MAX_ROWS;
            }

            $rows = self::buildListQuery($context, $params)
                ->order('ro.id', 'desc')
                ->page($pageNo, $pageSize)
                ->select()
                ->toArray();

            return [
                'lists' => self::formatRows($rows),
                'count' => $count,
                'page_no' => $pageNo,
                'page_size' => $pageSize,
                'extend' => [],
            ];
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    /**
     * 首次全额退款。资格检查在行锁内再次执行，防止并发重复扣款。
     * @return array{0:bool,1:string}
     */
    public static function refund(TenantContext $context, array $params, int $adminId): array
    {
        Db::startTrans();
        try {
            /** @var RechargeOrder $order */
            $order = FinanceTenantRepository::orders($context)->lock(true)->findOrEmpty((int)$params['recharge_id']);
            self::assertRefundableOrder($order);

            $existing = FinanceTenantRepository::records($context)->where([
                'order_type' => RefundEnum::ORDER_TYPE_RECHARGE,
                'order_id' => (int)$order->id,
            ])->lock(true)->findOrEmpty();
            if (!$existing->isEmpty()) {
                throw new \RuntimeException('订单已发起退款,退款失败请到退款记录重新退款');
            }

            $amountCents = MemberBalanceService::moneyToCents((string)$order->order_amount);
            $amount = $amountCents / 100;

            $order->refund_status = RechargeOrder::REFUND_STATUS_STARTED;
            $order->save();

            MemberBalanceService::applyInTransaction(
                $context,
                (int)$order->user_id,
                AccountLogEnum::USER_MONEY_DEC_RECHARGE_REFUND,
                AccountLogEnum::DEC,
                $amountCents,
                (string)$order->sn,
                '充值订单退款',
                [],
                $adminId,
                -$amountCents,
                '退款失败:用户余额已不足退款金额'
            );

            /** @var RefundRecord $record */
            $record = FinanceTenantRepository::createRecord($context, [
                'sn' => RefundRecord::generateSn(),
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

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            self::setError($e->getMessage());
            return [false, $e->getMessage()];
        }

        // 渠道调用必须发生在本地原子业务事务提交后，避免渠道已受理而本地整体回滚。
        return self::requestGatewayRefund($context, $order, $record, $log);
    }

    /**
     * 失败退款重试：复用 record，只新建 log，不再调整任何账户金额。
     * @return array{0:bool,1:string}
     */
    public static function refundAgain(TenantContext $context, array $params, int $adminId): array
    {
        $recordId = (int)$params['record_id'];
        $retryLock = self::retryLockName($context, $recordId);
        if (!self::acquireRetryLock($retryLock)) {
            $message = '退款正在处理中，请勿重复操作';
            self::setError($message);
            return [false, $message];
        }

        try {
            Db::startTrans();
            try {
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
                Db::commit();
            } catch (\Throwable $e) {
                Db::rollback();
                self::setError($e->getMessage());
                return [false, $e->getMessage()];
            }

            // 重试同样先提交 ERROR -> ING 和本次日志，再在事务外请求渠道。
            return self::requestGatewayRefund($context, $order, $record, $log);
        } finally {
            self::releaseRetryLock($retryLock);
        }
    }

    private static function retryLockName(TenantContext $context, int $recordId): string
    {
        $scope = TenantScope::fromTrustedContext(
            FinanceTenantContext::tenantId($context),
            $context->requestId,
        );
        return (new TenantLockNamespace($scope))->name('recharge:refund-retry:' . $recordId);
    }

    /** MySQL 会话级互斥覆盖完整渠道调用周期，避免快速失败时排队请求再次获准。 */
    private static function acquireRetryLock(string $lockName): bool
    {
        $rows = Db::query(
            'SELECT GET_LOCK(:lock_name, 0) AS acquired',
            ['lock_name' => $lockName]
        );
        return (int)($rows[0]['acquired'] ?? 0) === 1;
    }

    private static function releaseRetryLock(string $lockName): void
    {
        try {
            Db::query(
                'SELECT RELEASE_LOCK(:lock_name)',
                ['lock_name' => $lockName]
            );
        } catch (\Throwable) {
            // MySQL 连接关闭时命名锁会自动释放，不覆盖本次退款结果。
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
        if ((int)$order->refund_status === RechargeOrder::REFUND_STATUS_STARTED) {
            throw new \RuntimeException('订单已发起退款,退款失败请到退款记录重新退款');
        }
    }

    private static function createRefundLog(
        TenantContext $context,
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
    private static function requestGatewayRefund(
        TenantContext $context,
        RechargeOrder $order,
        RefundRecord $record,
        RefundLog $log
    ): array {
        $result = null;
        $gatewayError = null;
        try {
            $channel = match ((int)$order->pay_way) {
                RechargeOrder::PAY_WAY_WECHAT => 'wechat',
                RechargeOrder::PAY_WAY_ALIPAY => 'alipay',
                default => throw new \RuntimeException('支付方式异常'),
            };
            $result = (new PaymentServiceFactory())->refund($channel)->refund(
                $order->getData(),
                (string)$log->sn,
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

        // 渠道请求完成后使用新的短事务锁定本次记录和日志，原子落下结果。
        Db::startTrans();
        try {
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
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $message = $e->getMessage();
            self::setError($message);
            return [false, $message];
        }

        if ($gatewayError !== null) {
            self::setError($message);
            return [false, $message];
        }

        return [true, '操作成功'];
    }

    private static function encodeGatewayResult(mixed $result): string
    {
        if (is_string($result)) {
            return $result;
        }
        return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    private static function buildListQuery(TenantContext $context, array $params)
    {
        $tenantId = FinanceTenantContext::tenantId($context);
        $query = FinanceTenantRepository::orders($context, 'ro')
            ->join('member u', 'u.tenant_id = ro.tenant_id AND u.id = ro.user_id')
            ->where('u.tenant_id', $tenantId)
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

    private static function formatRows(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['id'] = (int)$row['id'];
            $row['pay_way'] = (int)$row['pay_way'];
            $row['pay_status'] = (int)$row['pay_status'];
            $row['refund_status'] = (int)$row['refund_status'];
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
        $sumPage = max(1, (int)ceil($count / $pageSize));
        return [
            'count' => $count,
            'page_size' => $pageSize,
            'sum_page' => $sumPage,
            'max_page' => (int)floor(self::EXPORT_MAX_ROWS / $pageSize),
            'all_max_size' => self::EXPORT_MAX_ROWS,
            'page_start' => 1,
            'page_end' => min($sumPage, 200),
            'file_name' => self::EXPORT_DEFAULT_NAME,
        ];
    }

    private static function export(TenantContext $context, array $params, int $count, int $pageSize): array
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

        $rows = self::buildListQuery($context, $params)
            ->order('ro.id', 'desc')
            ->limit($offset, $limit)
            ->select()
            ->toArray();
        $uri = XlsxExportService::createForTenant(
            $context,
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
            'url' => FileService::getFileUrl($uri),
            'file_name' => basename($uri),
        ];
    }

}
