<?php
declare(strict_types=1);

namespace app\Modules\Official\Notification\Application;

use app\Modules\Official\Notification\Contracts\DeliveryResult;
use app\Modules\Official\Notification\Contracts\VerificationResult;
use app\Modules\Official\Notification\Infrastructure\Persistence\NoticeTenantRepository;
use app\common\enum\notice\NoticeSceneEnum;
use PeanutAdmin\Kernel\Context\AuthenticatedMemberContext;
use app\common\service\notice\NoticeSmsSender;
use app\common\service\notice\NoticeTenantContext;
use app\common\service\notice\VerificationCodeSecret;
use app\common\execution\ExecutionContextAccess;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;
use PeanutAdmin\Kernel\Persistence\TransactionManager;

/**
 * 手机验证码发送与核验服务。
 */
class VerificationCodeService
{
    private const SEND_INTERVAL = 60;
    private const VALID_PERIOD = 300;

    public function __construct(
        private readonly NoticeSmsSender $sender,
        private readonly TransactionManager $transactions,
        private readonly ExecutionContextAccess $contexts,
        private readonly bool $developmentMode,
    ) {
    }

    public function send(
        TenantContext|TenantSystemContext $context,
        string $sceneCode,
        string $mobile
    ): DeliveryResult
    {
        NoticeTenantContext::verificationTenantId($this->contexts, $context, 'notice.verification.send');
        if (!$this->validMobile($mobile)) {
            return new DeliveryResult(false, '', '手机号格式不正确');
        }

        if (!NoticeSceneEnum::isValid($sceneCode)) {
            return new DeliveryResult(false, '', '验证码场景不存在');
        }

        $scene = NoticeTenantRepository::scenes($this->contexts, $context, 'notice.verification.send')
            ->where('code', $sceneCode)->findOrEmpty();
        if ($scene->isEmpty() || (int) $scene->sms_status !== $scene::STATUS_ENABLED) {
            return new DeliveryResult(false, '', '验证码场景未启用');
        }

        if ($this->sentRecently($context, $mobile)) {
            return new DeliveryResult(false, '', '同一手机号1分钟只能发送1条短信');
        }

        $templateId = trim((string) $scene->sms_template_id);
        $templateContent = trim((string) $scene->sms_content);
        if ($templateId === '' || $templateContent === '') {
            return new DeliveryResult(false, '', '短信模板未配置');
        }

        $code = $this->developmentMode
            ? '1234'
            : (string) random_int(
                10 ** (NoticeSceneEnum::CODE_LENGTH - 1),
                (10 ** NoticeSceneEnum::CODE_LENGTH) - 1
            );
        $content = $this->render($templateContent, ['code' => '****']);
        $sendTime = time();

        $log = null;
        $result = $this->sender->send(
            $context,
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
                $log = NoticeTenantRepository::createLog($this->contexts, $context, [
                    'template_id' => 0,
                    'scene_id' => (int)$scene->id,
                    'channel' => NoticeTenantRepository::LOG_CHANNEL_SMS,
                    'receiver' => $mobile,
                    'title' => (string)$scene->name,
                    'content' => $content,
                    'status' => NoticeTenantRepository::LOG_STATUS_PENDING,
                    'error' => '',
                    'extra' => $this->encodeExtra($templateId, []),
                    'send_time' => $sendTime,
                    'verify_code_hash' => VerificationCodeSecret::hash($code),
                    'is_verified' => NoticeTenantRepository::LOG_VERIFIED_NO,
                    'check_count' => 0,
                    'verified_time' => 0,
                    'provider' => $provider,
                ], 'notice.verification.send');
            }
        );
        if ($log === null) {
            return new DeliveryResult(false, $result['provider'], $result['error'], $result['result']);
        }
        $log->provider = $result['provider'];
        $log->status = $result['success'] ? NoticeTenantRepository::LOG_STATUS_SUCCESS : NoticeTenantRepository::LOG_STATUS_FAIL;
        $log->error = $result['error'];
        $log->extra = $this->encodeExtra($templateId, $result['result']);
        $log->save();

        return new DeliveryResult(
            $result['success'],
            $result['provider'],
            $result['error'],
            $result['result'],
        );
    }

    public function verify(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        string $sceneCode,
        string $mobile,
        string $code
    ): VerificationResult
    {
        NoticeTenantContext::verificationTenantId($this->contexts, $context, 'notice.verification.verify');
        if (!$this->validMobile($mobile)) {
            return new VerificationResult(false, '手机号格式不正确');
        }

        if (!NoticeSceneEnum::isValid($sceneCode)) {
            return new VerificationResult(false, '验证码场景不存在');
        }

        $scene = NoticeTenantRepository::scenes($this->contexts, $context, 'notice.verification.verify')
            ->where('code', $sceneCode)->findOrEmpty();
        if ($scene->isEmpty()) {
            return new VerificationResult(false, '验证码场景不存在');
        }

        return $this->transactions->run(function () use ($context, $scene, $mobile, $code): VerificationResult {
            $log = NoticeTenantRepository::logs($this->contexts, $context, 'notice.verification.verify')
                ->where('scene_id', (int) $scene->id)
                ->where('channel', NoticeTenantRepository::LOG_CHANNEL_SMS)
                ->where('receiver', $mobile)
                ->where('status', NoticeTenantRepository::LOG_STATUS_SUCCESS)
                ->order('send_time', 'desc')
                ->order('id', 'desc')
                ->lock(true)
                ->findOrEmpty();

            if ($log->isEmpty() || (int)$log->is_verified === NoticeTenantRepository::LOG_VERIFIED_YES) {
                return new VerificationResult(false, '验证码不存在或已使用');
            }

            $log->check_count = (int) $log->check_count + 1;
            if ((int) $log->send_time < time() - self::VALID_PERIOD) {
                $log->save();
                return new VerificationResult(false, '验证码已过期');
            }

            if (!VerificationCodeSecret::matches($code, (string)$log->verify_code_hash)) {
                $log->save();
                return new VerificationResult(false, '验证码不正确');
            }

            $log->is_verified = NoticeTenantRepository::LOG_VERIFIED_YES;
            $log->verified_time = time();
            $log->save();
            return new VerificationResult(true);
        });
    }

    private function sentRecently(TenantContext|TenantSystemContext $context, string $mobile): bool
    {
        return NoticeTenantRepository::logs($this->contexts, $context, 'notice.verification.send')
            ->where('channel', NoticeTenantRepository::LOG_CHANNEL_SMS)
            ->where('receiver', $mobile)
            ->where('scene_id', '>', 0)
            ->where('status', NoticeTenantRepository::LOG_STATUS_SUCCESS)
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
