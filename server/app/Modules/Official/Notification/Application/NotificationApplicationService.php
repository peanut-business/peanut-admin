<?php
declare(strict_types=1);

namespace app\Modules\Official\Notification\Application;

use app\common\application\BusinessException;
use app\common\http\PageResult;
use app\Modules\Official\Notification\Contracts\DeliveryResult;
use app\Modules\Official\Notification\Contracts\NotificationCommands;
use app\Modules\Official\Notification\Contracts\NotificationQueries;
use app\Modules\Official\Notification\Contracts\VerificationCodeCommands;
use app\Modules\Official\Notification\Contracts\VerificationResult;
use app\Modules\Official\Notification\Infrastructure\Persistence\NoticeTenantRepository;
use app\common\service\notice\NoticeChannelService;
use PeanutAdmin\Kernel\Context\AuthenticatedMemberContext;
use app\common\execution\CurrentExecutionContext;
use app\common\execution\ExecutionContextAccess;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;
use app\common\support\PaginationInput;

final class NotificationApplicationService implements NotificationCommands, NotificationQueries, VerificationCodeCommands
{
    public function __construct(
        private readonly CurrentExecutionContext $executionContext,
        private readonly VerificationCodeService $verificationCodes,
        private readonly ExecutionContextAccess $contexts,
        private readonly NoticeChannelService $channels,
    ) {
    }

    public function saveChannel(string $section, array $input): void
    {
        $this->channels->save($this->contexts, $this->executionContext->tenantAdmin(), $section, $input);
    }

    public function saveScene(array $params): void
    {
        $scene = NoticeTenantRepository::scenes($this->contexts, $this->executionContext->tenantAdmin())
            ->where('id', (int) $params['id'])
            ->findOrEmpty();
        if ($scene->isEmpty()) {
            throw BusinessException::notFound('NOTIFICATION_SCENE_NOT_FOUND', '通知场景不存在');
        }

        $scene->sms_template_id = trim((string) ($params['sms_template_id'] ?? ''));
        $scene->sms_content = trim((string) ($params['sms_content'] ?? ''));
        $scene->sms_status = (int) $params['sms_status'];
        $scene->save();
    }

    public function channelDetail(): array
    {
        return $this->channels->detail($this->executionContext->tenantAdmin());
    }

    public function scenes(): array
    {
        $list = NoticeTenantRepository::scenes($this->contexts, $this->executionContext->tenantAdmin())->field([
            'id', 'code', 'name', 'description', 'recipient', 'variables',
            'sms_template_id', 'sms_content', 'sms_status', 'update_time',
        ])->order('id', 'asc')->select()->toArray();

        return ['list' => $list, 'total' => count($list)];
    }

    public function sceneDetail(int $id): array
    {
        return NoticeTenantRepository::scenes($this->contexts, $this->executionContext->tenantAdmin())->where('id', $id)->findOrEmpty()->toArray();
    }

    public function sceneExists(int $id): bool
    {
        return !NoticeTenantRepository::scenes($this->contexts, $this->executionContext->tenantAdmin())->where('id', $id)->findOrEmpty()->isEmpty();
    }

    public function logs(array $params): PageResult
    {
        $query = NoticeTenantRepository::logQuery('l')
            ->leftJoin('notice_template t', 't.id = l.template_id')
            ->leftJoin('notice_scene s', 's.id = l.scene_id')
            ->field([
                'l.id', 'l.template_id', 'l.scene_id', 'l.channel', 'l.provider',
                'l.receiver', 'l.title', 'l.content', 'l.is_verified', 'l.check_count',
                'l.verified_time', 'l.status', 'l.error', 'l.send_time', 'l.create_time',
                't.name as template_name', 't.code as template_code',
                's.name as scene_name', 's.code as scene_code',
            ])
            ->where([]);

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

        $pagination = PaginationInput::from($params);
        $pageResult = $pagination->result($query->order('l.id', 'desc'));
        $pageResult = NoticeTenantRepository::arrayPage($pageResult);
        $list = $pageResult->items;

        return new PageResult($list, $pageResult->total, $pageResult->page, $pageResult->pageSize);
    }

    public function logDetail(int $id): array
    {
        return NoticeTenantRepository::logQuery('l')
            ->leftJoin('notice_template t', 't.id = l.template_id')
            ->leftJoin('notice_scene s', 's.id = l.scene_id')
            ->field([
                'l.id', 'l.template_id', 'l.scene_id', 'l.channel', 'l.provider',
                'l.receiver', 'l.title', 'l.content', 'l.is_verified', 'l.check_count',
                'l.verified_time', 'l.status', 'l.error', 'l.send_time', 'l.create_time',
                't.name as template_name', 't.code as template_code',
                's.name as scene_name', 's.code as scene_code',
            ])
            ->where([])
            ->where('l.id', $id)
            ->findOrEmpty()
            ->toArray();
    }

    public function sendCode(TenantContext|TenantSystemContext $context, string $sceneCode, string $mobile): DeliveryResult
    {
        return $this->verificationCodes->send($context, $sceneCode, $mobile);
    }

    public function verifyCode(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        string $sceneCode,
        string $mobile,
        string $code
    ): VerificationResult {
        return $this->verificationCodes->verify($context, $sceneCode, $mobile, $code);
    }
}
