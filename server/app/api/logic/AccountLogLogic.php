<?php
declare(strict_types=1);

namespace app\api\logic;

use app\common\logic\BaseLogic;
use app\common\model\member\MemberBalanceLog;

class AccountLogLogic extends BaseLogic
{
    /** 账户流水（只读，会员本人） */
    public static function lists(int $memberId, array $params): array
    {
        $page  = max(1, (int) ($params['page_no'] ?? 1));
        $limit = (int) ($params['page_size'] ?? 15);

        $query = MemberBalanceLog::where('member_id', $memberId);

        $count = $query->count();
        $lists = $query->order('id', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        return ['lists' => $lists, 'count' => $count, 'page_no' => $page, 'page_size' => $limit];
    }
}
