<?php
declare(strict_types=1);

namespace app\common\service\payment\contract;

use app\common\service\payment\dto\PrepayRequest;
use app\common\service\payment\dto\PrepayResult;

interface PrepayGatewayInterface
{
    public function prepay(PrepayRequest $request): PrepayResult;
}
