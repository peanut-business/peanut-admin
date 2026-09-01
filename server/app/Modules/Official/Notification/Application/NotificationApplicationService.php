<?php
declare(strict_types=1);

namespace app\Modules\Official\Notification\Application;

use app\common\application\BusinessException;
use app\common\execution\SystemExecutionContext;
use app\common\http\PageResult;
use app\Modules\Official\Notification\Contracts\DeliveryResult;
use app\Modules\Official\Notification\Contracts\NotificationCommands;
use app\Modules\Official\Notification\Contracts\NotificationQueries;
use app\Modules\Official\Notification\Contracts\VerificationCodeCommands;
use app\Modules\Official\Notification\Contracts\VerificationResult;
use app\Modules\Official\Notification\Infrastructure\Persistence\NoticeTenantRepository;
use app\Modules\Official\Notification\Model\NoticeLog;
use app\Modules\Official\Notification\Model\NoticeScene;
use app\common\service\notice\NoticeChannelService;
use app\common\service\member\AuthenticatedMemberContext;
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
    ) {
    }

    public function provisionTenantDefaults(SystemExecutionContext $context): void
    {
        $system = $context->system;
        if ($system->tenantId < 1
            || $system->actorKey !== 'platform.tenant-bootstrap'
            || $system->operation !== 'notification.provision-tenant-defaults'
            || $system->operationId === '') {
            throw new \DomainException('NOTIFICATION_PROVISION_CONTEXT_INVALID');
        }
        NoticeScene::provisionDefaults([
            ['login_code', '登录验证码', '用户使用手机号验证码登录', '您的登录验证码是${code}，五分钟内有效。'],
            ['bind_mobile', '绑定手机验证码', '用户首次绑定手机号', '您的绑定手机验证码是${code}，五分钟内有效。'],
            ['change_mobile', '变更手机验证码', '用户更换已绑定手机号', '您的变更手机验证码是${code}，五分钟内有效。'],
            ['reset_password', '找回密码验证码', '用户通过手机号重置密码', '您的找回密码验证码是${code}，五分钟内有效。'],
        ]);
    }

    public function saveChannel(string $section, array $input): void
    {
        NoticeChannelService::save($this->contexts, $this->executionContext->tenantAdmin(), $section, $input);
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
        return NoticeChannelService::detail($this->executionContext->tenantAdmin());
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
        $query = NoticeLog::alias('l')
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
        $list = array_map(
            static fn($item): array => $item instanceof \think\Model ? $item->toArray() : (array) $item,
            $pageResult->items,
        );

        return new PageResult($list, $pageResult->total, $pageResult->page, $pageResult->pageSize);
    }

    public function logDetail(int $id): array
    {
        return NoticeLog::alias('l')
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
