<?php
declare(strict_types=1);

namespace app\adminapi\logic\notice;

use app\common\logic\BaseLogic;
use app\common\model\notice\NoticeLog;
use app\common\service\notice\NoticeTenantContext;
use PeanutAdmin\Kernel\Auth\TenantContext;

/**
 * 通知发送日志 Logic（只读）
 */
class NoticeLogLogic extends BaseLogic
{
    private const SAFE_FIELDS = [
        'l.id', 'l.template_id', 'l.scene_id', 'l.channel', 'l.provider',
        'l.receiver', 'l.title', 'l.content', 'l.is_verified', 'l.check_count',
        'l.verified_time', 'l.status', 'l.error', 'l.send_time', 'l.create_time',
        't.name as template_name', 't.code as template_code',
        's.name as scene_name', 's.code as scene_code',
    ];

    /**
     * 列表（分页）
     * @param array<string,mixed> $params
     */
    public static function lists(TenantContext $context, array $params): array
    {
        $tenantId = NoticeTenantContext::tenantId($context);
        $query = NoticeLog::alias('l')
            ->leftJoin('notice_template t', 't.tenant_id = l.tenant_id AND t.id = l.template_id')
            ->leftJoin('notice_scene s', 's.tenant_id = l.tenant_id AND s.id = l.scene_id')
            ->field(self::SAFE_FIELDS)
            ->where('l.tenant_id', $tenantId);

        if (!empty($params['receiver'])) {
            $query->whereLike('l.receiver', '%' . $params['receiver'] . '%');
        }
        if (isset($params['channel']) && $params['channel'] !== '') {
            $query->where('l.channel', (int) $params['channel']);
        }
        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('l.status', (int) $params['status']);
        }
        if (isset($params['scene_id']) && $params['scene_id'] !== '') {
            $query->where('l.scene_id', (int) $params['scene_id']);
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
    public static function detail(TenantContext $context, int $id): array
    {
        $tenantId = NoticeTenantContext::tenantId($context);
        $log = NoticeLog::alias('l')
            ->leftJoin('notice_template t', 't.tenant_id = l.tenant_id AND t.id = l.template_id')
            ->leftJoin('notice_scene s', 's.tenant_id = l.tenant_id AND s.id = l.scene_id')
            ->field(self::SAFE_FIELDS)
            ->where('l.tenant_id', $tenantId)
            ->where('l.id', $id)
            ->findOrEmpty();

        return $log->toArray();
    }
}
