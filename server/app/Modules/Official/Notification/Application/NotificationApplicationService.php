<?php
declare(strict_types=1);

namespace app\Modules\Official\Notification\Application;

use app\Modules\Official\Notification\Contracts\DeliveryResult;
use app\Modules\Official\Notification\Contracts\NotificationCommands;
use app\Modules\Official\Notification\Contracts\NotificationQueries;
use app\Modules\Official\Notification\Contracts\VerificationCodeCommands;
use app\Modules\Official\Notification\Contracts\VerificationResult;
use app\Modules\Official\Notification\Model\NoticeLog;
use app\common\service\notice\NoticeChannelService;
use app\common\service\notice\NoticeTenantContext;
use app\common\service\notice\NoticeTenantRepository;
use app\common\service\notice\VerificationCodeService;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\TenantContext as KernelTenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

final class NotificationApplicationService implements NotificationCommands, NotificationQueries, VerificationCodeCommands
{
    public function saveChannel(TenantContext $context, string $section, array $input): void
    {
        NoticeChannelService::save($context, $section, $input);
    }

    public function saveScene(TenantContext $context, array $params): void
    {
        $scene = NoticeTenantRepository::scenes($context)
            ->where('id', (int) $params['id'])
            ->findOrEmpty();
        if ($scene->isEmpty()) {
            throw new \RuntimeException('通知场景不存在');
        }

        $scene->sms_template_id = trim((string) ($params['sms_template_id'] ?? ''));
        $scene->sms_content = trim((string) ($params['sms_content'] ?? ''));
        $scene->sms_status = (int) $params['sms_status'];
        $scene->save();
    }

    public function channelDetail(TenantContext $context): array
    {
        return NoticeChannelService::detail($context);
    }

    public function scenes(TenantContext $context): array
    {
        $list = NoticeTenantRepository::scenes($context)->field([
            'id', 'code', 'name', 'description', 'recipient', 'variables',
            'sms_template_id', 'sms_content', 'sms_status', 'update_time',
        ])->order('id', 'asc')->select()->toArray();

        return ['list' => $list, 'total' => count($list)];
    }

    public function sceneDetail(TenantContext $context, int $id): array
    {
        return NoticeTenantRepository::scenes($context)->where('id', $id)->findOrEmpty()->toArray();
    }

    public function sceneExists(TenantContext $context, int $id): bool
    {
        return !NoticeTenantRepository::scenes($context)->where('id', $id)->findOrEmpty()->isEmpty();
    }

    public function logs(TenantContext $context, array $params): array
    {
        $tenantId = NoticeTenantContext::tenantId($context);
        $query = NoticeLog::alias('l')
            ->leftJoin('notice_template t', 't.tenant_id = l.tenant_id AND t.id = l.template_id')
            ->leftJoin('notice_scene s', 's.tenant_id = l.tenant_id AND s.id = l.scene_id')
            ->field([
                'l.id', 'l.template_id', 'l.scene_id', 'l.channel', 'l.provider',
                'l.receiver', 'l.title', 'l.content', 'l.is_verified', 'l.check_count',
                'l.verified_time', 'l.status', 'l.error', 'l.send_time', 'l.create_time',
                't.name as template_name', 't.code as template_code',
                's.name as scene_name', 's.code as scene_code',
            ])
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
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = max(1, (int) ($params['limit'] ?? 15));
        $list = $query->order('l.id', 'desc')->page($page, $limit)->select()->toArray();

        return ['total' => $total, 'list' => $list];
    }

    public function logDetail(TenantContext $context, int $id): array
    {
        $tenantId = NoticeTenantContext::tenantId($context);
        return NoticeLog::alias('l')
            ->leftJoin('notice_template t', 't.tenant_id = l.tenant_id AND t.id = l.template_id')
            ->leftJoin('notice_scene s', 's.tenant_id = l.tenant_id AND s.id = l.scene_id')
            ->field([
                'l.id', 'l.template_id', 'l.scene_id', 'l.channel', 'l.provider',
                'l.receiver', 'l.title', 'l.content', 'l.is_verified', 'l.check_count',
                'l.verified_time', 'l.status', 'l.error', 'l.send_time', 'l.create_time',
                't.name as template_name', 't.code as template_code',
                's.name as scene_name', 's.code as scene_code',
            ])
            ->where('l.tenant_id', $tenantId)
            ->where('l.id', $id)
            ->findOrEmpty()
            ->toArray();
    }

    public function sendCode(TenantContext|TenantSystemContext $context, string $sceneCode, string $mobile): DeliveryResult
    {
        return (new VerificationCodeService())->send($context, $sceneCode, $mobile);
    }

    public function verifyCode(
        \app\common\service\member\AuthenticatedMemberContext|KernelTenantContext|TenantSystemContext $context,
        string $sceneCode,
        string $mobile,
        string $code
    ): VerificationResult {
        return (new VerificationCodeService())->verify($context, $sceneCode, $mobile, $code);
    }
}
