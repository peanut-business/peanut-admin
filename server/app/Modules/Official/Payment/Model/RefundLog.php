<?php
declare(strict_types=1);

namespace app\Modules\Official\Payment\Model;

use app\common\enum\RefundEnum;
use app\common\model\BaseModel;
use think\facade\Db;

/**
 * 退款日志模型
 */
class RefundLog extends BaseModel
{
    protected $name = 'refund_log';

    /** Provider request identity remains globally unique across Tenants. */
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
        return (string)Db::name('tenant_member')
            ->where('tenant_id', (int)($data['tenant_id'] ?? 0))
            ->where('id', (int)$data['handle_id'])
            ->value('display_name');
    }

    /** 退款状态文字 */
    public function getRefundStatusTextAttr($value, $data): string
    {
        return (string) RefundEnum::getStatusDesc($data['refund_status'] ?? 0);
    }
}
