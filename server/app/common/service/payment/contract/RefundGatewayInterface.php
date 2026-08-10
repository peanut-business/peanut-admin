<?php
declare(strict_types=1);

namespace app\common\service\payment\contract;

interface RefundGatewayInterface
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    /** 渠道可能已受理但本次没有可信结果，调用方必须保持退款中。 */
    public const ERROR_RESULT_UNKNOWN = 10001;

    /** @return array{status:string,transaction_id:string,receipt:array<string,mixed>} */
    public function refund(array $order, string $refundSn, int $refundAmountCents): array;

    /** @return array{status:string,transaction_id:string,receipt:array<string,mixed>} */
    public function query(array $order, string $refundSn): array;
}
