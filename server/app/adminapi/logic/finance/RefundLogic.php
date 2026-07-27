<?php
declare(strict_types=1);

namespace app\adminapi\logic\finance;

use app\common\enum\RefundEnum;
use app\common\logic\BaseLogic;
use app\common\model\refund\RefundLog;
use app\common\model\refund\RefundRecord;
use app\common\service\FileService;
use think\facade\Db;

/**
 * 退款 Logic
 */
class RefundLogic extends BaseLogic
{
    /**
     * 退款统计（四个金额：累计/退款中/成功/失败）
     */
    public static function stat(): array
    {
        $records = RefundRecord::select()->toArray();

        $total = $ing = $success = $error = 0;
        foreach ($records as $record) {
            $total += $record['order_amount'];
            match ($record['refund_status']) {
                RefundEnum::REFUND_ING     => $ing     += $record['order_amount'],
                RefundEnum::REFUND_SUCCESS => $success += $record['order_amount'],
                RefundEnum::REFUND_ERROR   => $error   += $record['order_amount'],
                default                    => null,
            };
        }

        return [
            'total'   => round($total, 2),
            'ing'     => round($ing, 2),
            'success' => round($success, 2),
            'error'   => round($error, 2),
        ];
    }

    /**
     * 构建基础查询（不含 refund_status 筛选），返回带别名的 Query 对象
     */
    private static function buildBaseQuery(array $params): \think\db\Query
    {
        $query = RefundRecord::alias('r')
            ->leftJoin('member m', 'm.id = r.user_id')
            ->field('r.*, m.nickname, m.avatar');

        if (!empty($params['sn'])) {
            $query->where('r.sn', trim((string) $params['sn']));
        }
        if (!empty($params['order_sn'])) {
            $query->where('r.order_sn', trim((string) $params['order_sn']));
        }
        if (isset($params['refund_type']) && $params['refund_type'] !== '') {
            $query->where('r.refund_type', (int) $params['refund_type']);
        }
        if (!empty($params['user_info'])) {
            $kw = trim((string) $params['user_info']);
            $query->where(function ($q) use ($kw) {
                $q->whereLike('m.nickname', "%{$kw}%")
                  ->whereOr('m.sn', $kw)
                  ->whereOr('m.mobile', $kw);
            });
        }
        if (!empty($params['start_time'])) {
            $query->where('r.create_time', '>=', (int) $params['start_time']);
        }
        if (!empty($params['end_time'])) {
            $query->where('r.create_time', '<=', (int) $params['end_time']);
        }

        return $query;
    }

    /**
     * 退款记录分页列表
     * @param array<string,mixed> $params
     * @return array{lists:array,count:int,page:int,limit:int,extend:array}
     */
    public static function lists(array $params): array
    {
        // extend：各状态数量，不含 refund_status 过滤
        $baseQuery = self::buildBaseQuery($params);
        $extend    = $baseQuery
            ->fieldRaw(
                'count(r.id) as total'
                . ', count(if(r.refund_status=' . RefundEnum::REFUND_ING     . ',1,null)) as ing'
                . ', count(if(r.refund_status=' . RefundEnum::REFUND_SUCCESS . ',1,null)) as `success`'
                . ', count(if(r.refund_status=' . RefundEnum::REFUND_ERROR   . ',1,null)) as error'
            )
            ->findOrEmpty()
            ->toArray();

        // 主列表查询，加入 refund_status 筛选
        $listQuery = self::buildBaseQuery($params);
        if (isset($params['refund_status']) && $params['refund_status'] !== '') {
            $listQuery->where('r.refund_status', (int) $params['refund_status']);
        }

        $count = $listQuery->count();
        $page  = max(1, (int) ($params['page']  ?? 1));
        $limit = max(1, (int) ($params['limit'] ?? 15));

        $lists = $listQuery->order('r.id', 'desc')
            ->page($page, $limit)
            ->append(['refund_type_text', 'refund_status_text', 'refund_way_text'])
            ->select()
            ->toArray();

        foreach ($lists as &$item) {
            $item['avatar'] = FileService::getFileUrl($item['avatar'] ?? '');
        }
        unset($item);

        return [
            'lists'  => $lists,
            'count'  => $count,
            'page'   => $page,
            'limit'  => $limit,
            'extend' => [
                'total'   => (int) ($extend['total']   ?? 0),
                'ing'     => (int) ($extend['ing']     ?? 0),
                'success' => (int) ($extend['success'] ?? 0),
                'error'   => (int) ($extend['error']   ?? 0),
            ],
        ];
    }

    /**
     * 退款日志（某条 record 的所有日志，最新在前）
     */
    public static function refundLog(int $recordId): array
    {
        return (new RefundLog())
            ->order(['id' => 'desc'])
            ->where('record_id', $recordId)
            ->hidden(['refund_msg'])
            ->append(['handler', 'refund_status_text'])
            ->select()
            ->toArray();
    }
}
