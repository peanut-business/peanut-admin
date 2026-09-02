<?php
declare(strict_types=1);

namespace app\Modules\Official\Payment\Contracts;

use app\common\service\payment\dto\CallbackRequest;
use app\common\service\payment\dto\PaymentEvent;

interface RechargeCommands
{
    public function create(object $context, int $memberId, array $params): array;

    public function prepay(
        object $context,
        int $memberId,
        int $orderId,
        int $payWay,
        string $notifyUrl,
        string $clientIp = '',
        string $openid = ''
    ): array;

    public function parseCallback(string $channel, array $config, CallbackRequest $request): PaymentEvent;

    public function settleVerifiedCallback(int $paymentBindingId, PaymentEvent $event, int $payWay): bool;
}
