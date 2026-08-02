<?php
declare(strict_types=1);

namespace app\common\service\notice;

use app\common\enum\notice\NoticeSceneEnum;
use app\common\model\notice\NoticeLog;
use app\common\model\notice\NoticeScene;
use app\common\service\ConfigService;
use app\common\service\notice\driver\sms\AliyunSms;
use app\common\service\notice\driver\sms\SmsDriver;
use app\common\service\notice\driver\sms\TencentSms;
use think\facade\Db;

/**
 * 手机验证码发送与核验服务。
 */
class VerificationCodeService
{
    private const SEND_INTERVAL = 60;
    private const VALID_PERIOD = 300;

    private string $error = '';

    public function send(string $sceneCode, string $mobile): bool
    {
        $this->error = '';
        if (!$this->validMobile($mobile)) {
            $this->error = '手机号格式不正确';
            return false;
        }

        if (!NoticeSceneEnum::isValid($sceneCode)) {
            $this->error = '验证码场景不存在';
            return false;
        }

        $scene = NoticeScene::where('code', $sceneCode)->findOrEmpty();
        if ($scene->isEmpty() || (int) $scene->sms_status !== NoticeScene::STATUS_ENABLED) {
            $this->error = '验证码场景未启用';
            return false;
        }

        if ($this->sentRecently($mobile)) {
            $this->error = '同一手机号1分钟只能发送1条短信';
            return false;
        }

        $templateId = trim((string) $scene->sms_template_id);
        $templateContent = trim((string) $scene->sms_content);
        if ($templateId === '' || $templateContent === '') {
            $this->error = '短信模板未配置';
            return false;
        }

        $provider = strtolower(trim((string) ConfigService::get('notice', 'sms_default', '')));
        $config = $this->providerConfig($provider);
        if ($config === null) {
            return false;
        }

        $driver = $this->makeDriver($provider, $config);
        if ($driver === null) {
            $this->error = '短信服务商不受支持';
            return false;
        }

        $code = (string) random_int(
            10 ** (NoticeSceneEnum::CODE_LENGTH - 1),
            (10 ** NoticeSceneEnum::CODE_LENGTH) - 1
        );
        $content = $this->render($templateContent, ['code' => $code]);
        $sendTime = time();

        $log = NoticeLog::create([
            'template_id' => 0,
            'scene_id' => (int) $scene->id,
            'channel' => NoticeLog::CHANNEL_SMS,
            'receiver' => $mobile,
            'title' => (string) $scene->name,
            'content' => $content,
            'status' => NoticeLog::STATUS_PENDING,
            'error' => '',
            'extra' => $this->encodeExtra($templateId, []),
            'send_time' => $sendTime,
            'verify_code' => $code,
            'is_verified' => NoticeLog::VERIFIED_NO,
            'check_count' => 0,
            'verified_time' => 0,
            'provider' => $provider,
        ]);

        try {
            $success = $driver->send($mobile, $templateId, ['code' => $code]);
            $result = $driver->getResult();
            $this->error = $success ? '' : $driver->getError();
        } catch (\Throwable $exception) {
            $success = false;
            $result = [];
            $this->error = $exception->getMessage();
        }

        $log->status = $success ? NoticeLog::STATUS_SUCCESS : NoticeLog::STATUS_FAIL;
        $log->error = $success ? '' : $this->error;
        $log->extra = $this->encodeExtra($templateId, $result);
        $log->save();

        return $success;
    }

    public function verify(string $sceneCode, string $mobile, string $code): bool
    {
        $this->error = '';
        if (!$this->validMobile($mobile)) {
            $this->error = '手机号格式不正确';
            return false;
        }

        if (!NoticeSceneEnum::isValid($sceneCode)) {
            $this->error = '验证码场景不存在';
            return false;
        }

        $scene = NoticeScene::where('code', $sceneCode)->findOrEmpty();
        if ($scene->isEmpty()) {
            $this->error = '验证码场景不存在';
            return false;
        }

        return Db::transaction(function () use ($scene, $mobile, $code): bool {
            $log = NoticeLog::where('scene_id', (int) $scene->id)
                ->where('channel', NoticeLog::CHANNEL_SMS)
                ->where('receiver', $mobile)
                ->where('status', NoticeLog::STATUS_SUCCESS)
                ->where('is_verified', NoticeLog::VERIFIED_NO)
                ->order('send_time', 'desc')
                ->order('id', 'desc')
                ->lock(true)
                ->findOrEmpty();

            if ($log->isEmpty()) {
                $this->error = '验证码不存在或已使用';
                return false;
            }

            $log->check_count = (int) $log->check_count + 1;
            if ((int) $log->send_time < time() - self::VALID_PERIOD) {
                $log->save();
                $this->error = '验证码已过期';
                return false;
            }

            if (!hash_equals((string) $log->verify_code, $code)) {
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

    private function sentRecently(string $mobile): bool
    {
        return NoticeLog::where('channel', NoticeLog::CHANNEL_SMS)
            ->where('receiver', $mobile)
            ->where('scene_id', '>', 0)
            ->where('status', NoticeLog::STATUS_SUCCESS)
            ->where('send_time', '>', time() - self::SEND_INTERVAL)
            ->count() > 0;
    }

    /** @return array<string,mixed>|null */
    private function providerConfig(string $provider): ?array
    {
        if (!in_array($provider, [NoticeLog::PROVIDER_ALIYUN, NoticeLog::PROVIDER_TENCENT], true)) {
            $this->error = '短信服务商未配置';
            return null;
        }

        $raw = ConfigService::get('notice', 'sms_' . $provider, '');
        $config = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?? []);
        if ((int) ($config['status'] ?? 0) !== 1) {
            $this->error = '短信服务未开启';
            return null;
        }

        $required = $provider === NoticeLog::PROVIDER_ALIYUN
            ? ['access_key_id', 'access_key_secret', 'sign_name']
            : ['secret_id', 'secret_key', 'sdk_app_id', 'sign_name'];
        foreach ($required as $field) {
            if (trim((string) ($config[$field] ?? '')) === '') {
                $this->error = '短信服务商配置不完整';
                return null;
            }
        }

        return $config;
    }

    /** @param array<string,mixed> $config */
    private function makeDriver(string $provider, array $config): ?SmsDriver
    {
        return match ($provider) {
            NoticeLog::PROVIDER_ALIYUN => new AliyunSms($config),
            NoticeLog::PROVIDER_TENCENT => new TencentSms($config),
            default => null,
        };
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
