<?php
declare(strict_types=1);

namespace app\common\service\payment\contract;

use app\common\service\payment\dto\TransportResponse;

/** 支付渠道 HTTP 传输边界；验收可注入内存实现，生产默认使用 cURL。 */
interface PaymentTransportInterface
{
    public function request(string $method, string $url, array $headers, string $body = ''): TransportResponse;
}
