<?php
declare(strict_types=1);

namespace app\api\application;

use app\common\http\PageResult;
use app\Modules\Official\Member\Contracts\Dto\MemberBalanceMutation;
use app\Modules\Official\Member\Contracts\MemberBalanceCommands;
use app\Modules\Official\Member\Contracts\MemberQueries;
use app\Modules\Official\Oauth\ModuleProvider as OAuthModuleProvider;
use app\common\enum\AccountLogEnum;
use app\common\enum\UserTerminalEnum;
use app\common\application\ApplicationService;
use app\common\contract\audit\AuditResource;
use app\common\execution\ExecutionContextAccess;
use app\common\persistence\TransactionalExecution;
use app\Modules\Official\Payment\Model\PaymentScene;
use app\Modules\Official\Payment\Model\RechargeOrder;
use app\common\service\MemberBalanceService;
use app\common\service\audit\AuditContractHost;
use app\common\service\external\ExternalTenantContext;
use app\common\service\finance\FinanceTenantContext;
use app\common\service\finance\FinanceTenantRepository;
use app\common\service\finance\RechargeTenantSettingService;
use app\common\service\payment\PaymentChannelGrantService;
use app\common\service\payment\PaymentServiceFactory;
use app\common\service\payment\dto\PaymentEvent;
use app\common\service\payment\dto\PrepayRequest;
use app\common\support\PaginationInput;
use PeanutAdmin\Kernel\Audit\AuditOutcome;

/** 用户充值订单和幂等入账状态机。 */
class RechargeApplicationService extends ApplicationService
{
    private const MAX_AMOUNT_CENTS = 99999999;

    public function __construct(
        private readonly AuditContractHost $audit,
        private readonly MemberQueries $members,
        private readonly MemberBalanceCommands $memberBalances,
    ) {
    }

    public function config(object $context, int $memberId, int $terminal): array|false
    {
        try {
            self::assertTerminal($terminal);
            $member = $this->members->balanceSnapshot($context, $memberId);
            if ($member === null) {
                throw new \RuntimeException('用户不存在');
            }

            $setting = RechargeTenantSettingService::config($context);
            $scenes = RechargeTenantSettingService::enabledScenes($context, $terminal);
            $scenes = array_values(array_filter(
                $scenes,
                static fn(array $scene): bool => RechargeTenantSettingService::channelConfigured(
                    $context,
                    (int)$scene['pay_way']
                )
            ));

            return [
                'status' => (int)$setting['status'],
                'min_amount' => self::moneyString($setting['min_amount']),
                'balance' => MemberBalanceService::centsToMoney($member->balanceCents),
                'terminal' => $terminal,
                'channels' => array_map(static fn(array $scene): array => [
                    'pay_way' => (int)$scene['pay_way'],
                    'name' => PaymentScene::getPayWayDesc((int)$scene['pay_way']),
                    'is_default' => (int)$scene['is_default'],
                ], $scenes),
            ];
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public function create(object $context, int $memberId, array $params): array|false
    {
        try {
            $terminal = (int)$params['terminal'];
            self::assertTerminal($terminal);
            $setting = RechargeTenantSettingService::config($context);
            if ((int)$setting['status'] !== 1) {
                throw new \RuntimeException('充值功能未开启');
            }

            $amountCents = self::moneyToCents((string)$params['amount']);
            $minCents = self::moneyToCents((string)$setting['min_amount']);
            if ($amountCents <= 0 || $amountCents < $minCents) {
                throw new \RuntimeException('充值金额不能低于最低充值金额');
            }
            $configuredMax = self::moneyToCents((string)$setting['max_amount']);
            $maxCents = min(self::MAX_AMOUNT_CENTS, $configuredMax);
            if ($amountCents > $maxCents) {
                throw new \RuntimeException('充值金额超过单次上限');
            }

            if ($this->members->balanceSnapshot($context, $memberId) === null) {
                throw new \RuntimeException('用户不存在');
            }
            $defaultScene = RechargeTenantSettingService::defaultScene($context, $terminal);
            if ($defaultScene === null
                || !RechargeTenantSettingService::channelConfigured($context, (int)$defaultScene['pay_way'])) {
                throw new \RuntimeException('当前终端暂无可用支付方式');
            }

            $order = app(TransactionalExecution::class)->run(function () use (
                $context,
                $memberId,
                $defaultScene,
                $amountCents,
                $terminal,
            ): RechargeOrder {
                $order = FinanceTenantRepository::createOrder($context, [
                    'sn' => RechargeOrder::generateSn(),
                    'user_id' => $memberId,
                    'pay_sn' => '',
                    'pay_way' => (int)$defaultScene['pay_way'],
                    'pay_status' => RechargeOrder::PAY_STATUS_UNPAID,
                    'pay_time' => null,
                    'order_amount' => self::centsToMoney($amountCents),
                    'order_terminal' => $terminal,
                    'transaction_id' => null,
                    'refund_status' => RechargeOrder::REFUND_STATUS_NONE,
                ]);
                $this->recordPublicFinanceAudit(
                    $context,
                    'public.recharge.created',
                    'recharge.create',
                    $order,
                    ['business_member_id' => $memberId, 'amount_cents' => $amountCents],
                );
                return $order;
            });

            return self::formatOrder($order->toArray());
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    /** 锁定本人未支付订单并固化本次支付渠道、授权和请求号。 */
    public function prepareAttempt(object $context, int $memberId, int $orderId, int $payWay): array|false
    {
        try {
            return app(TransactionalExecution::class)->run(function () use ($context, $memberId, $orderId, $payWay): array {
                /** @var RechargeOrder $order */
                $order = FinanceTenantRepository::orders($context)->lock(true)->findOrEmpty($orderId);
                self::assertOwnedUnpaid($order, $memberId);
                $terminal = (int)$order->order_terminal;
                if (!PaymentScene::supports($terminal, $payWay)) {
                    throw new \RuntimeException('当前终端未启用该支付方式');
                }
                $scene = RechargeTenantSettingService::scene($context, $terminal, $payWay);
                if ($scene === null) {
                    throw new \RuntimeException('当前终端未启用该支付方式');
                }
                if (!RechargeTenantSettingService::channelConfigured($context, $payWay)) {
                    throw new \RuntimeException('支付渠道未启用或配置不完整');
                }
                $provider = PaymentChannelGrantService::providerForPayWay($payWay);
                $grant = PaymentChannelGrantService::activeGrantForTenant($context, $provider, true);

                $order->pay_way = $payWay;
                $order->pay_sn = RechargeOrder::generatePaySn();
                $order->payment_binding_id = (int)$grant['external_binding_id'];
                $order->payment_grant_id = (int)$grant['id'];
                $order->payment_merchant_account_ref = (string)($grant['merchant_account_ref'] ?? '');
                $order->payment_merchant_group_ref = (string)($grant['merchant_group_ref'] ?? '');
                $order->save();
                $this->recordPublicFinanceAudit(
                    $context,
                    'public.recharge.payment-prepared',
                    'recharge.prepare-payment',
                    $order,
                    [
                        'business_member_id' => $memberId,
                        'pay_way' => $payWay,
                        'payment_binding_id' => (int)$grant['external_binding_id'],
                    ],
                );
                return [
                    'order' => $order->toArray(),
                    'grant' => $grant,
                ];
            });
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    /** 创建真实渠道预支付参数；渠道调用通过 PaymentServiceFactory 边界完成。 */
    public function prepay(
        object $context,
        int $memberId,
        int $orderId,
        int $payWay,
        string $notifyUrl,
        string $clientIp = '',
        string $openid = ''
    ): array|false {
        $attempt = self::prepareAttempt($context, $memberId, $orderId, $payWay);
        if ($attempt === false) {
            return false;
        }

        try {
            $order = $attempt['order'];
            $grant = $attempt['grant'];
            if ($payWay === PaymentScene::PAY_WAY_WECHAT
                && in_array((int)$order['order_terminal'], [1, 2], true)) {
                $openid = (new OAuthModuleProvider())->queries()->wechatSubjectForMember(
                    $context,
                    $memberId,
                    (int)$order['order_terminal']
                );
                if ($openid === '') {
                    throw new \RuntimeException('当前微信终端尚未绑定可用身份');
                }
            }
            $channel = match ($payWay) {
                PaymentScene::PAY_WAY_WECHAT => 'wechat',
                PaymentScene::PAY_WAY_ALIPAY => 'alipay',
                default => throw new \RuntimeException('支付渠道不受支持'),
            };
            $notifyUrl = rtrim($notifyUrl, '/')
                . '/api/payment/notify/' . $channel . '/' . (string)$grant['callback_key'];
            $request = new PrepayRequest(
                (string)$order['sn'],
                self::moneyToCents((string)$order['order_amount']),
                (int)$order['order_terminal'],
                $notifyUrl,
                '账户充值',
                'CNY',
                $openid,
                $clientIp
            );
            $result = (new PaymentServiceFactory($grant['config']))->prepay($channel)->prepay($request);
            return [
                'order' => self::formatOrder($order),
                'payment' => $result->toArray(),
            ];
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public function detail(object $context, int $memberId, int $orderId): array|false
    {
        try {
            $order = FinanceTenantRepository::orders($context)->where(['id' => $orderId, 'user_id' => $memberId])->findOrEmpty();
            if ($order->isEmpty()) {
                throw new \RuntimeException('充值订单不存在');
            }
            return self::formatOrder($order->toArray());
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public function lists(object $context, int $memberId, array $params): PageResult
    {
        $pageNo = max(1, (int)($params['page_no'] ?? 1));
        $pageSize = max(1, min(100, (int)($params['page_size'] ?? 15)));
        $query = FinanceTenantRepository::orders($context)->where('user_id', $memberId);
        $pageResult = PaginationInput::from($params)->result($query->order('id', 'desc'));
        $rows = array_map(static fn($item): array => $item instanceof \think\Model ? $item->toArray() : (array) $item, $pageResult->items);
        return new PageResult(
            array_map([self::class, 'formatOrder'], $rows),
            $pageResult->total,
            $pageResult->page,
            $pageResult->pageSize,
        );
    }

    /**
     * 可信渠道回调的唯一入账入口。
     * @param PaymentEvent|array{order_sn:string,pay_way:int,transaction_id:string,amount_cents?:int,amount?:string|float|int,currency:string,status?:string} $payment
     */
    public function settleVerifiedCallback(int $paymentBindingId, PaymentEvent $event, int $payWay): bool
    {
        try {
            if ($paymentBindingId < 1) {
                throw new \RuntimeException('支付回调授权缺失');
            }
            $order = RechargeOrder::where('sn', $event->orderSn())
                ->where('payment_binding_id', $paymentBindingId)
                ->findOrEmpty();
            if ($order->isEmpty()) {
                throw new \RuntimeException('充值订单不存在');
            }
            $context = ExternalTenantContext::verified(
                (int)$order->tenant_id,
                'payment.settle',
                'payment:' . hash('sha256', $event->orderSn() . ':' . (string)$paymentBindingId)
            );
            return self::settle($context, [
                'order_sn' => $event->orderSn(),
                'pay_way' => $payWay,
                'transaction_id' => $event->transactionId(),
                'amount_cents' => $event->amount(),
                'currency' => $event->currency(),
                'status' => $event->status(),
                'payment_binding_id' => $paymentBindingId,
            ]);
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public function settle(object $context, PaymentEvent|array $payment): bool
    {
        try {
            return app(TransactionalExecution::class)->run(function () use ($context, $payment): bool {
                if ($payment instanceof PaymentEvent) {
                    $payment = [
                        'order_sn' => $payment->orderSn(),
                        'pay_way' => self::channelToPayWay($payment->channel()),
                        'transaction_id' => $payment->transactionId(),
                        'amount_cents' => $payment->amount(),
                        'currency' => $payment->currency(),
                        'status' => $payment->status(),
                    ];
                }
                $orderSn = trim((string)($payment['order_sn'] ?? ''));
                $transactionId = trim((string)($payment['transaction_id'] ?? ''));
                $payWay = (int)($payment['pay_way'] ?? 0);
                $bindingId = (int)($payment['payment_binding_id'] ?? 0);
                $currency = strtoupper(trim((string)($payment['currency'] ?? '')));
                if ($orderSn === '' || $transactionId === '') {
                    throw new \RuntimeException('支付回调订单或交易流水缺失');
                }
                if ($bindingId < 1) {
                    throw new \RuntimeException('支付回调授权缺失');
                }
                if ($currency !== 'CNY') {
                    throw new \RuntimeException('支付币种不一致');
                }
                if (($payment['status'] ?? 'success') !== 'success') {
                    throw new \RuntimeException('支付状态尚未成功');
                }

                if (FinanceTenantContext::tenantId($context) < 1) {
                    throw new \RuntimeException('支付回调租户无效');
                }

                /** @var RechargeOrder $order */
                $order = FinanceTenantRepository::orders($context)->where('sn', $orderSn)->lock(true)->findOrEmpty();
                if ($order->isEmpty()) {
                    throw new \RuntimeException('充值订单不存在');
                }
                $callbackCents = array_key_exists('amount_cents', $payment)
                    ? (int)$payment['amount_cents']
                    : self::moneyToCents((string)($payment['amount'] ?? ''));
                $orderCents = self::moneyToCents((string)$order->order_amount);
                if ($callbackCents !== $orderCents) {
                    throw new \RuntimeException('支付金额不一致');
                }
                if ((int)$order->pay_way !== $payWay) {
                    throw new \RuntimeException('支付渠道不一致');
                }
                if ((int)($order->payment_binding_id ?? 0) !== $bindingId
                    || (int)($order->payment_grant_id ?? 0) < 1) {
                    throw new \RuntimeException('支付渠道授权不一致');
                }

                if ((int)$order->pay_status === RechargeOrder::PAY_STATUS_PAID) {
                    if ((string)$order->transaction_id !== $transactionId) {
                        throw new \RuntimeException('支付交易流水冲突');
                    }
                    return true;
                }

                $conflict = FinanceTenantRepository::orders($context)->where('transaction_id', $transactionId)
                    ->where('id', '<>', (int)$order->id)->lock(true)->findOrEmpty();
                if (!$conflict->isEmpty()) {
                    throw new \RuntimeException('支付交易流水已被使用');
                }

                $this->memberBalances->applyInTransaction(
                    $context,
                    new MemberBalanceMutation(
                        (int)$order->user_id,
                        AccountLogEnum::USER_MONEY_INC_RECHARGE,
                        AccountLogEnum::INC,
                        $orderCents,
                        (string)$order->sn,
                        '用户充值',
                        [],
                        0,
                        $orderCents,
                    ),
                );

                $order->pay_status = RechargeOrder::PAY_STATUS_PAID;
                $order->pay_time = time();
                $order->transaction_id = $transactionId;
                $order->save();

                $this->recordPublicFinanceAudit(
                    $context,
                    'public.recharge.settled',
                    'recharge.settle',
                    $order,
                    [
                        'business_member_id' => (int)$order->user_id,
                        'amount_cents' => $orderCents,
                        'pay_way' => $payWay,
                        'payment_binding_id' => $bindingId,
                    ],
                );

                return true;
            });
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    private static function assertOwnedUnpaid(RechargeOrder $order, int $memberId): void
    {
        if ($order->isEmpty() || (int)$order->user_id !== $memberId) {
            throw new \RuntimeException('充值订单不存在');
        }
        if ((int)$order->pay_status !== RechargeOrder::PAY_STATUS_UNPAID) {
            throw new \RuntimeException('充值订单已支付');
        }
    }

    /** @param array<string,mixed> $metadata */
    private function recordPublicFinanceAudit(
        object $context,
        string $eventType,
        string $operation,
        RechargeOrder $order,
        array $metadata,
    ): void {
        $tenantId = FinanceTenantContext::tenantId($context);
        $current = ExecutionContextAccess::current();
        $requestId = $current !== null && $current->tenantId() === $tenantId
            ? $current->requestId()
            : trim((string)($context->requestId ?? $context->operationId ?? ''));
        if ($requestId === '') {
            throw new \DomainException('RECHARGE_AUDIT_REQUEST_ID_REQUIRED');
        }
        $this->audit->recordTenantSystem(
            $tenantId,
            $eventType,
            $operation,
            $requestId,
            ['subject_type' => 'business_member'] + $metadata,
            AuditOutcome::Success,
            null,
            new AuditResource('recharge_order', (string)$order->id),
        );
    }

    private static function assertTerminal(int $terminal): void
    {
        if (!UserTerminalEnum::isValid($terminal)) {
            throw new \RuntimeException('支付终端不支持');
        }
    }

    private static function channelToPayWay(string $channel): int
    {
        return match (strtolower(trim($channel))) {
            'wechat' => PaymentScene::PAY_WAY_WECHAT,
            'alipay' => PaymentScene::PAY_WAY_ALIPAY,
            default => throw new \RuntimeException('支付渠道不受支持'),
        };
    }

    private static function moneyToCents(string $amount): int
    {
        $amount = trim($amount);
        if (!preg_match('/^(?:0|[1-9]\d{0,8})(?:\.\d{1,2})?$/', $amount)) {
            throw new \RuntimeException('金额格式错误');
        }
        [$yuan, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        return ((int)$yuan * 100) + (int)str_pad($fraction, 2, '0');
    }

    private static function centsToMoney(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private static function moneyString(mixed $amount): string
    {
        return self::centsToMoney(self::moneyToCents((string)$amount));
    }

    private static function formatOrder(array $row): array
    {
        return [
            'id' => (int)$row['id'],
            'sn' => (string)$row['sn'],
            'pay_way' => (int)$row['pay_way'],
            'pay_way_text' => PaymentScene::getPayWayDesc((int)$row['pay_way']),
            'pay_status' => (int)$row['pay_status'],
            'pay_status_text' => (int)$row['pay_status'] === RechargeOrder::PAY_STATUS_PAID ? '已支付' : '未支付',
            'order_amount' => self::moneyString($row['order_amount']),
            'order_terminal' => (int)$row['order_terminal'],
            'terminal_text' => UserTerminalEnum::getDesc((int)$row['order_terminal']),
            'transaction_id' => (string)($row['transaction_id'] ?? ''),
            'pay_time' => empty($row['pay_time']) ? '' : date('Y-m-d H:i:s', (int)$row['pay_time']),
            'create_time' => empty($row['create_time']) ? '' : (is_numeric($row['create_time']) ? date('Y-m-d H:i:s', (int)$row['create_time']) : (string)$row['create_time']),
        ];
    }
}
