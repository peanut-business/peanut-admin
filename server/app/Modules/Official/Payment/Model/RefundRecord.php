<?php
declare(strict_types=1);

namespace app\Modules\Official\Payment\Model;

use app\common\enum\RefundEnum;
use app\common\model\BaseModel;

/**
 * 退款记录模型
 */
class RefundRecord extends BaseModel
{
    protected $name = 'refund_record';

    /** Merchant-facing refund number remains globally unique across Tenants. */
    public static function generateSn(): string
    {
        do {
            $sn = date('YmdHis') . str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (self::where('sn', $sn)->count() > 0);

        return $sn;
    }

    /** 退款类型文字 */
    public function getRefundTypeTextAttr($value, $data): string
    {
        return (string) RefundEnum::getTypeDesc($data['refund_type'] ?? 0);
    }

    /** 退款状态文字 */
    public function getRefundStatusTextAttr($value, $data): string
    {
        return (string) RefundEnum::getStatusDesc($data['refund_status'] ?? 0);
    }

    /** 退款方式文字 */
    public function getRefundWayTextAttr($value, $data): string
    {
        return (string) RefundEnum::getWayDesc($data['refund_way'] ?? 0);
    }
}
