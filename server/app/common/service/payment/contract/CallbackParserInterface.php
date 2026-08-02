<?php
declare(strict_types=1);

namespace app\common\service\payment\contract;

use app\common\service\payment\dto\CallbackRequest;
use app\common\service\payment\dto\PaymentEvent;

/** 只负责验签和标准化；实现不得更新订单、余额或用户数据。 */
interface CallbackParserInterface
{
    public function parse(CallbackRequest $request): PaymentEvent;
}
