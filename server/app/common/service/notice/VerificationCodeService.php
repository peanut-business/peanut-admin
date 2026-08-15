<?php
declare(strict_types=1);

namespace app\common\service\notice;

use app\common\enum\notice\NoticeSceneEnum;
use app\common\model\notice\NoticeLog;
use app\common\service\member\AuthenticatedMemberContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;
use think\facade\Db;

/**
 * 手机验证码发送与核验服务。
 */
class VerificationCodeService
{
    private const SEND_INTERVAL = 60;
    private const VALID_PERIOD = 300;

    private string $error = '';

    public function __construct(private readonly NoticeSmsSender $sender = new ApplicationNoticeSmsSender())
    {
    }

    public function send(TenantContext|TenantSystemContext $context, string $sceneCode, string $mobile): bool
    {
        NoticeTenantContext::verificationTenantId($context, 'notice.verification.send');
        $this->error = '';
        if (!$this->validMobile($mobile)) {
            $this->error = '手机号格式不正确';
            return false;
        }

        if (!NoticeSceneEnum::isValid($sceneCode)) {
            $this->error = '验证码场景不存在';
            return false;
        }

        $scene = NoticeTenantRepository::scenes($context, 'notice.verification.send')
            ->where('code', $sceneCode)->findOrEmpty();
        if ($scene->isEmpty() || (int) $scene->sms_status !== $scene::STATUS_ENABLED) {
            $this->error = '验证码场景未启用';
            return false;
        }

        if ($this->sentRecently($context, $mobile)) {
            $this->error = '同一手机号1分钟只能发送1条短信';
            return false;
        }

        $templateId = trim((string) $scene->sms_template_id);
        $templateContent = trim((string) $scene->sms_content);
        if ($templateId === '' || $templateContent === '') {
            $this->error = '短信模板未配置';
            return false;
        }

        $code = (string) random_int(
            10 ** (NoticeSceneEnum::CODE_LENGTH - 1),
            (10 ** NoticeSceneEnum::CODE_LENGTH) - 1
        );
        $content = $this->render($templateContent, ['code' => '****']);
        $sendTime = time();

        $log = null;
        $result = $this->sender->send(
            $mobile,
            $templateId,
            ['code' => $code],
            function (string $provider) use (
                &$log,
                $context,
                $scene,
                $mobile,
                $content,
                $sendTime,
                $code,
                $templateId
            ): void {
                $log = NoticeTenantRepository::createLog($context, [
                    'template_id' => 0,
                    'scene_id' => (int)$scene->id,
                    'channel' => NoticeLog::CHANNEL_SMS,
                    'receiver' => $mobile,
                    'title' => (string)$scene->name,
                    'content' => $content,
                    'status' => NoticeLog::STATUS_PENDING,
                    'error' => '',
                    'extra' => $this->encodeExtra($templateId, []),
                    'send_time' => $sendTime,
                    'verify_code_hash' => VerificationCodeSecret::hash($code),
                    'is_verified' => NoticeLog::VERIFIED_NO,
                    'check_count' => 0,
                    'verified_time' => 0,
                    'provider' => $provider,
                ], 'notice.verification.send');
            }
        );
        $this->error = $result['error'];
        if ($log === null) {
            return false;
        }
        $log->provider = $result['provider'];
        $log->status = $result['success'] ? NoticeLog::STATUS_SUCCESS : NoticeLog::STATUS_FAIL;
        $log->error = $result['error'];
        $log->extra = $this->encodeExtra($templateId, $result['result']);
        $log->save();

        return $result['success'];
    }

    public function verify(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        string $sceneCode,
        string $mobile,
        string $code
    ): bool
    {
        NoticeTenantContext::verificationTenantId($context, 'notice.verification.verify');
        $this->error = '';
        if (!$this->validMobile($mobile)) {
            $this->error = '手机号格式不正确';
            return false;
        }

        if (!NoticeSceneEnum::isValid($sceneCode)) {
            $this->error = '验证码场景不存在';
            return false;
        }

        $scene = NoticeTenantRepository::scenes($context, 'notice.verification.verify')
            ->where('code', $sceneCode)->findOrEmpty();
        if ($scene->isEmpty()) {
            $this->error = '验证码场景不存在';
            return false;
        }

        return Db::transaction(function () use ($context, $scene, $mobile, $code): bool {
            $log = NoticeTenantRepository::logs($context, 'notice.verification.verify')
                ->where('scene_id', (int) $scene->id)
                ->where('channel', NoticeLog::CHANNEL_SMS)
                ->where('receiver', $mobile)
                ->where('status', NoticeLog::STATUS_SUCCESS)
                ->order('send_time', 'desc')
                ->order('id', 'desc')
                ->lock(true)
                ->findOrEmpty();

            if ($log->isEmpty() || (int)$log->is_verified === NoticeLog::VERIFIED_YES) {
                $this->error = '验证码不存在或已使用';
                return false;
            }

            $log->check_count = (int) $log->check_count + 1;
            if ((int) $log->send_time < time() - self::VALID_PERIOD) {
                $log->save();
                $this->error = '验证码已过期';
                return false;
            }

            if (!VerificationCodeSecret::matches($code, (string)$log->verify_code_hash)) {
                $log->save();
                $this->error = '验证码不正确';
                return false;
            }

            $log->is_verified = NoticeLog::VERIFIED_YES;
            $log->verified_time = time();
            $log->save();
            return true;
        });
    }

    public function getError(): string
    {
        return $this->error;
    }

    private function sentRecently(TenantContext|TenantSystemContext $context, string $mobile): bool
    {
        return NoticeTenantRepository::logs($context, 'notice.verification.send')
            ->where('channel', NoticeLog::CHANNEL_SMS)
            ->where('receiver', $mobile)
            ->where('scene_id', '>', 0)
            ->where('status', NoticeLog::STATUS_SUCCESS)
            ->where('send_time', '>', time() - self::SEND_INTERVAL)
            ->count() > 0;
    }

    /** @param array<string,string> $variables */
    private function render(string $content, array $variables): string
    {
        foreach ($variables as $name => $value) {
            $content = str_replace(['${' . $name . '}', '{' . $name . '}'], $value, $content);
        }
        return $content;
    }

    /** @param array<string,mixed> $result */
    private function encodeExtra(string $templateId, array $result): string
    {
        return (string) json_encode([
            'provider_template_id' => $templateId,
            'provider_result' => $result,
        ], JSON_UNESCAPED_UNICODE);
    }

    private function validMobile(string $mobile): bool
    {
        return preg_match('/^1[3-9]\d{9}$/', $mobile) === 1;
    }
}
