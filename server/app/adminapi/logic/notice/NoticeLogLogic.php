<?php
declare(strict_types=1);

namespace app\adminapi\logic\notice;

use app\common\logic\BaseLogic;
use app\common\model\notice\NoticeLog;

/**
 * 通知发送日志 Logic（只读）
 */
class NoticeLogLogic extends BaseLogic
{
    /**
     * 列表（分页）
     * @param array<string,mixed> $params
     */
    public static function lists(array $params): array
    {
        $query = NoticeLog::alias('l')
            ->leftJoin('notice_template t', 't.id = l.template_id')
            ->field('l.*, t.name as template_name, t.code as template_code');

        if (!empty($params['receiver'])) {
            $query->whereLike('l.receiver', '%' . $params['receiver'] . '%');
        }
        if (isset($params['channel']) && $params['channel'] !== '') {
            $query->where('l.channel', (int) $params['channel']);
        }
        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('l.status', (int) $params['status']);
        }
        if (!empty($params['start_time'])) {
            $query->where('l.send_time', '>=', (int) $params['start_time']);
        }
        if (!empty($params['end_time'])) {
            $query->where('l.send_time', '<=', (int) $params['end_time']);
        }

        $total = $query->count();
        $page  = max(1, (int) ($params['page']  ?? 1));
        $limit = max(1, (int) ($params['limit'] ?? 15));

        $list = $query->order('l.id', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        return ['total' => $total, 'list' => $list];
    }

    /**
     * 日志详情
     */
    public static function detail(int $id): array
    {
        $log = NoticeLog::alias('l')
            ->leftJoin('notice_template t', 't.id = l.template_id')
            ->field('l.*, t.name as template_name, t.code as template_code')
            ->where('l.id', $id)
            ->findOrEmpty();

        return $log->toArray();
    }
}
