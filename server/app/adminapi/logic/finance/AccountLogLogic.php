<?php
declare(strict_types=1);

namespace app\adminapi\logic\finance;

use app\common\logic\BaseLogic;
use app\common\model\member\MemberBalanceLog;

/**
 * 账户流水（余额变动）Logic（只读）
 *
 * 数据源为 pa_member_balance_log —— 会员余额的每一次变动都会落一条记录。
 * 目前来源仅「手动调整」(source_type=0)；后续接入充值/消费时在此扩展映射即可。
 */
class AccountLogLogic extends BaseLogic
{
    /** 变动方向：收入 / 支出（由 change_amount 正负推导） */
    public const DIRECTION_INCOME  = 1;
    public const DIRECTION_EXPENSE = 2;

    /**
     * 列表（分页），联表取会员昵称/编号
     * @param array<string,mixed> $params
     * @return array{lists:array,count:int,page:int,limit:int}
     */
    public static function lists(array $params): array
    {
        $query = MemberBalanceLog::alias('log')
            ->leftJoin('member m', 'm.id = log.member_id')
            ->field('log.*, m.nickname as member_nickname, m.sn as member_sn');

        // 会员关键词：昵称 / 编号 / 手机号
        if (!empty($params['keyword'])) {
            $kw = trim((string) $params['keyword']);
            $query->where(function ($q) use ($kw) {
                $q->whereLike('m.nickname', "%{$kw}%")
                  ->whereOr('m.sn', $kw)
                  ->whereOr('m.mobile', $kw);
            });
        }
        // 来源类型
        if (isset($params['source_type']) && $params['source_type'] !== '') {
            $query->where('log.source_type', (int) $params['source_type']);
        }
        // 收支方向
        if (isset($params['direction']) && $params['direction'] !== '') {
            $direction = (int) $params['direction'];
            if ($direction === self::DIRECTION_INCOME) {
                $query->where('log.change_amount', '>', 0);
            } elseif ($direction === self::DIRECTION_EXPENSE) {
                $query->where('log.change_amount', '<', 0);
            }
        }
        // 时间区间（按变动时间 create_time）
        if (!empty($params['start_time'])) {
            $query->where('log.create_time', '>=', (int) $params['start_time']);
        }
        if (!empty($params['end_time'])) {
            $query->where('log.create_time', '<=', (int) $params['end_time']);
        }

        $count = $query->count();
        $page  = max(1, (int) ($params['page']  ?? 1));
        $limit = max(1, (int) ($params['limit'] ?? 15));

        $lists = $query->order('log.id', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        foreach ($lists as &$row) {
            $row['direction'] = ((float) $row['change_amount']) >= 0
                ? self::DIRECTION_INCOME
                : self::DIRECTION_EXPENSE;
        }
        unset($row);

        return ['lists' => $lists, 'count' => $count, 'page' => $page, 'limit' => $limit];
    }
}
