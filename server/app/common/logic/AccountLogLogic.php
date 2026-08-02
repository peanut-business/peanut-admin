<?php
declare(strict_types=1);

namespace app\common\logic;

use app\common\enum\AccountLogEnum;
use app\common\model\member\Member;
use app\common\model\member\MemberBalanceLog;

/** 统一记录用户账户流水，供余额调整、充值和退款复用。 */
class AccountLogLogic extends BaseLogic
{
    public static function add(
        int $memberId,
        int $changeType,
        int $action,
        float $changeAmount,
        string $sourceSn = '',
        string $remark = '',
        array $extra = [],
        int $adminId = 0
    ): MemberBalanceLog|false {
        $member = Member::findOrEmpty($memberId);
        if ($member->isEmpty()) {
            return false;
        }

        $changeObject = AccountLogEnum::getChangeObject($changeType);
        if ($changeObject === false) {
            return false;
        }

        $leftAmount = (float)$member->user_money;
        return MemberBalanceLog::create([
            'sn' => MemberBalanceLog::generateSn(),
            'member_id' => $memberId,
            'change_object' => $changeObject,
            'change_type' => $changeType,
            'action' => $action,
            'change_amount' => abs(round($changeAmount, 2)),
            'left_amount' => $leftAmount,
            // Peanut 旧字段保留为兼容镜像，不再承载权威语义。
            'after_amount' => $leftAmount,
            'source_type' => 0,
            'source_sn' => $sourceSn,
            'remark' => $remark,
            'extra' => $extra === [] ? '' : json_encode($extra, JSON_UNESCAPED_UNICODE),
            'admin_id' => $adminId,
        ]);
    }
}
