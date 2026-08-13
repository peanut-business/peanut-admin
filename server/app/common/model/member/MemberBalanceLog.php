<?php
declare(strict_types=1);

namespace app\common\model\member;

use app\common\model\BaseModel;
use app\common\service\member\MemberTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;
use think\model\concern\SoftDelete;

class MemberBalanceLog extends BaseModel
{
    use SoftDelete;

    protected $name = 'member_balance_log';
    protected $deleteTime = 'delete_time';

    /** 生成 20 位数字流水号。 */
    public static function generateSn(TenantContext|TenantSystemContext $context): string
    {
        do {
            $sn = date('YmdHis') . str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (MemberTenantRepository::balanceLogs($context)->withTrashed()->where('sn', $sn)->count() > 0);

        return $sn;
    }
}
