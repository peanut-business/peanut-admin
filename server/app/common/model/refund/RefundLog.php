<?php
declare(strict_types=1);

namespace app\common\model\refund;

use app\common\enum\RefundEnum;
use app\common\model\BaseModel;
use app\common\model\auth\Admin;

/**
 * 退款日志模型
 */
class RefundLog extends BaseModel
{
    protected $name = 'refund_log';

    public static function generateSn(): string
    {
        do {
            $sn = date('YmdHis') . str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (self::where('sn', $sn)->count() > 0);

        return $sn;
    }

    /** 操作人名称（管理员） */
    public function getHandlerAttr($value, $data): string
    {
        if (empty($data['handle_id'])) {
            return '系统';
        }
        return (string) Admin::where('id', $data['handle_id'])->value('nickname');
    }

    /** 退款状态文字 */
    public function getRefundStatusTextAttr($value, $data): string
    {
        return (string) RefundEnum::getStatusDesc($data['refund_status'] ?? 0);
    }
}
