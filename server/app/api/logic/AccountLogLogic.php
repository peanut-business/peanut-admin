<?php
declare(strict_types=1);

namespace app\api\logic;

use app\Modules\Official\Member\ModuleProvider as MemberModuleProvider;
use app\common\logic\BaseLogic;
use app\common\service\member\AuthenticatedMemberContext;

class AccountLogLogic extends BaseLogic
{
    /** 账户流水（只读，会员本人） */
    public static function lists(AuthenticatedMemberContext $context, int $memberId, array $params): array
    {
        $page  = max(1, (int) ($params['page_no'] ?? 1));
        $limit = (int) ($params['page_size'] ?? 15);

        $result = (new MemberModuleProvider())->queries()
            ->balanceLogsForMember($context, $memberId, $page, $limit);

        return [
            'lists' => $result->items,
            'count' => $result->total,
            'page_no' => $result->page,
            'page_size' => $result->pageSize,
        ];
    }
}
