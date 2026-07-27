<?php
declare(strict_types=1);

namespace app\common\model\finance;

use app\common\model\BaseModel;

/**
 * 充值订单 Model
 *
 * 对应表 pa_recharge_order
 */
class RechargeOrder extends BaseModel
{
    protected $name = 'recharge_order';

    /** 支付状态 */
    public const STATUS_PENDING = 0; // 待支付
    public const STATUS_PAID    = 1; // 已支付
    public const STATUS_FAILED  = 2; // 已关闭/失败

    /** 支付方式 */
    public const PAY_WAY_WECHAT = 1; // 微信支付
    public const PAY_WAY_ALIPAY = 2; // 支付宝
}
