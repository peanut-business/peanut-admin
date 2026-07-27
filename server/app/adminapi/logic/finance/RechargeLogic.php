<?php
declare(strict_types=1);

namespace app\adminapi\logic\finance;

use app\common\logic\BaseLogic;
use app\common\model\finance\RechargeOrder;

/**
 * 充值订单 Logic（管理后台只读）
 *
 * 提供分页列表，支持按会员、状态、支付方式、时间筛选。
 */
class RechargeLogic extends BaseLogic
{
    /** 支付状态标签 */
    public const STATUS_LABELS = [
        RechargeOrder::STATUS_PENDING => '待支付',
        RechargeOrder::STATUS_PAID    => '已支付',
        RechargeOrder::STATUS_FAILED  => '已关闭',
    ];

    /** 支付方式标签 */
    public const PAY_WAY_LABELS = [
        RechargeOrder::PAY_WAY_WECHAT => '微信支付',
        RechargeOrder::PAY_WAY_ALIPAY => '支付宝',
    ];

    /**
     * 列表（分页），联表取会员昵称/编号
     * @param array<string,mixed> $params
     * @return array{lists:array,count:int,page:int,limit:int}
     */
    public static function lists(array $params): array
    {
        $query = RechargeOrder::alias('r')
            ->leftJoin('member m', 'm.id = r.member_id')
            ->field('r.*, m.nickname as member_nickname, m.sn as member_sn');

        // 会员关键词：昵称 / 编号 / 手机号
        if (!empty($params['keyword'])) {
            $kw = trim((string) $params['keyword']);
            $query->where(function ($q) use ($kw) {
                $q->whereLike('m.nickname', "%{$kw}%")
                  ->whereOr('m.sn', $kw)
                  ->whereOr('m.mobile', $kw);
            });
        }

        // 状态筛选
        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('r.status', (int) $params['status']);
        }

        // 支付方式
        if (!empty($params['pay_way'])) {
            $query->where('r.pay_way', (int) $params['pay_way']);
        }

        // 时间区间
        if (!empty($params['start_time'])) {
            $query->where('r.create_time', '>=', (int) $params['start_time']);
        }
        if (!empty($params['end_time'])) {
            $query->where('r.create_time', '<=', (int) $params['end_time']);
        }

        $count = $query->count();
        $page  = max(1, (int) ($params['page']  ?? 1));
        $limit = max(1, (int) ($params['limit'] ?? 15));

        $lists = $query->order('r.id', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        foreach ($lists as &$row) {
            $row['status_label']  = self::STATUS_LABELS[$row['status']]  ?? '';
            $row['pay_way_label'] = self::PAY_WAY_LABELS[$row['pay_way']] ?? '';
        }
        unset($row);

        return ['lists' => $lists, 'count' => $count, 'page' => $page, 'limit' => $limit];
    }
}
