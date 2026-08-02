<?php
declare(strict_types=1);

namespace app\common\service\notice;

use app\common\model\notice\NoticeLog;
use app\common\model\notice\NoticeTemplate;
use app\common\service\ConfigService;
use app\common\service\notice\driver\mail\SmtpMail;
use app\common\service\notice\driver\sms\AliyunSms;
use app\common\service\notice\driver\sms\SmsDriver;
use app\common\service\notice\driver\sms\TencentSms;

/**
 * 通知发送服务
 *
 * 用法：
 *   NoticeService::send('verify_code', 'sms', '13800000000', ['code' => '123456']);
 *   NoticeService::send('welcome_email', 'mail', 'user@example.com', ['nickname' => 'Alice']);
 *
 * 渠道配置从 pa_config 读取：
 *   type=notice, name=sms_default      → 'aliyun' | 'tencent'
 *   type=notice, name=sms_aliyun       → JSON { access_key_id, access_key_secret, sign_name }
 *   type=notice, name=sms_tencent      → JSON { secret_id, secret_key, sdk_app_id, sign_name, region }
 *   type=notice, name=mail_smtp        → JSON { host, port, username, password, from_name, encryption }
 */
class NoticeService
{
    private string $error = '';

    /**
     * 按模板 code 发送通知（自动选择渠道）
     *
     * @param string              $templateCode 模板标识
     * @param string              $receiver     接收者（手机号/邮箱）
     * @param array<string,mixed> $vars         模板变量
     * @param int|null            $channel      强制指定渠道（null 则用模板渠道）
     */
    public static function send(
        string $templateCode,
        string $receiver,
        array  $vars = [],
        ?int   $channel = null
    ): bool {
        return (new self())->doSend($templateCode, $receiver, $vars, $channel);
    }

    /**
     * 查询渠道是否已配置（用于前端页面展示）
     * @return array{sms: bool, mail: bool}
     */
    public static function channelStatus(): array
    {
        $smsDefault = ConfigService::get('notice', 'sms_default', '');
        $smsCfg     = $smsDefault
            ? json_decode((string) ConfigService::get('notice', 'sms_' . $smsDefault, '{}'), true)
            : [];
        $mailCfg    = json_decode((string) ConfigService::get('notice', 'mail_smtp', '{}'), true);

        return [
            'sms'  => (int) ($smsCfg['status'] ?? 0) === 1
                && !empty($smsCfg['access_key_id'] ?? $smsCfg['secret_id'] ?? ''),
            'mail' => !empty($mailCfg['host'] ?? ''),
        ];
    }

    private function doSend(
        string $templateCode,
        string $receiver,
        array  $vars,
        ?int   $channel
    ): bool {
        /** @var NoticeTemplate|null $tpl */
        $tpl = NoticeTemplate::where('code', $templateCode)
            ->where('is_disable', 0)
            ->whereNull('delete_time')
            ->findOrEmpty();

        if ($tpl->isEmpty()) {
            $this->error = "通知模板不存在或已禁用: {$templateCode}";
            return false;
        }

        $ch = $channel ?? $tpl->channel;
        ['title' => $title, 'content' => $content] = $tpl->render($vars);

        $logData = [
            'template_id' => $tpl->id,
            'channel'     => $ch,
            'receiver'    => $receiver,
            'title'       => $title,
            'content'     => $content,
            'status'      => NoticeLog::STATUS_PENDING,
            'send_time'   => time(),
        ];

        $success = match ($ch) {
            NoticeTemplate::CHANNEL_SMS   => $this->sendSms($receiver, $tpl->content, $vars),
            NoticeTemplate::CHANNEL_EMAIL => $this->sendMail($receiver, $title, $content),
            default => false,
        };

        $logData['status'] = $success ? NoticeLog::STATUS_SUCCESS : NoticeLog::STATUS_FAIL;
        $logData['error']  = $success ? '' : $this->error;
        NoticeLog::create($logData);

        return $success;
    }

    private function sendSms(string $mobile, string $templateContent, array $vars): bool
    {
        // SMS 发送使用服务商模板 code（存于 notice_template.content 字段作为服务商 templateCode）
        $provider   = ConfigService::get('notice', 'sms_default', 'aliyun');
        $cfgRaw     = ConfigService::get('notice', 'sms_' . $provider, '');
        $cfg        = is_array($cfgRaw) ? $cfgRaw : (json_decode((string) $cfgRaw, true) ?? []);

        if ((int) ($cfg['status'] ?? 0) !== 1) {
            $this->error = '短信服务未开启';
            return false;
        }

        $driver = $this->makeSmsDriver((string) $provider, $cfg);
        if ($driver === null) {
            $this->error = "短信渠道未配置或不支持: {$provider}";
            return false;
        }

        // templateContent 在 SMS 场景下存储的是服务商模板 code（如 SMS_123456789）
        $ok = $driver->send($mobile, $templateContent, $vars);
        if (!$ok) {
            $this->error = $driver->getError();
        }
        return $ok;
    }

    private function sendMail(string $to, string $subject, string $body): bool
    {
        $cfgRaw = ConfigService::get('notice', 'mail_smtp', '');
        $cfg    = is_array($cfgRaw) ? $cfgRaw : (json_decode((string) $cfgRaw, true) ?? []);

        if (empty($cfg['host'])) {
            $this->error = '邮件渠道未配置';
            return false;
        }

        $mailer = new SmtpMail($cfg);
        $ok     = $mailer->send($to, $subject, $body);
        if (!$ok) {
            $this->error = $mailer->getError();
        }
        return $ok;
    }

    private function makeSmsDriver(string $provider, array $cfg): ?SmsDriver
    {
        return match ($provider) {
            'aliyun'  => new AliyunSms($cfg),
            'tencent' => new TencentSms($cfg),
            default   => null,
        };
    }

    public function getError(): string
    {
        return $this->error;
    }
}
