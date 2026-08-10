<?php
declare(strict_types=1);

namespace app\common\service\notice;

use app\common\model\config\Config;
use app\common\service\ConfigService;
use app\common\service\notice\driver\sms\AliyunSms;
use app\common\service\notice\driver\sms\SmsDriver;
use app\common\service\notice\driver\sms\TencentSms;
use think\facade\Db;

/** 应用短信凭据、默认 Provider、驱动选择与回执脱敏的唯一 Host。 */
final class NoticeChannelService
{
    private const PROVIDERS = ['aliyun', 'tencent'];

    public static function detail(): array
    {
        $default = strtolower(trim((string)ConfigService::get('notice', 'sms_default', '')));
        $aliyun = self::config('aliyun');
        $tencent = self::config('tencent');
        $active = $default === 'aliyun' ? $aliyun : ($default === 'tencent' ? $tencent : []);

        return [
            'sms_default' => $default,
            'sms_aliyun' => [
                'access_key_id' => (string)($aliyun['access_key_id'] ?? ''),
                'access_key_secret' => empty($aliyun['access_key_secret']) ? '' : '******',
                'sign_name' => (string)($aliyun['sign_name'] ?? ''),
                'status' => (int)($aliyun['status'] ?? 0),
            ],
            'sms_tencent' => [
                'secret_id' => (string)($tencent['secret_id'] ?? ''),
                'secret_key' => empty($tencent['secret_key']) ? '' : '******',
                'sdk_app_id' => (string)($tencent['sdk_app_id'] ?? ''),
                'sign_name' => (string)($tencent['sign_name'] ?? ''),
                'region' => (string)($tencent['region'] ?? 'ap-guangzhou'),
                'status' => (int)($tencent['status'] ?? 0),
            ],
            'status' => ['sms' => in_array($default, self::PROVIDERS, true)
                && self::complete($default, $active)],
        ];
    }

    public static function save(string $section, array $input): void
    {
        Db::transaction(function () use ($section, $input): void {
            Config::where('type', 'notice')->lock(true)->select();
            self::saveLocked($section, $input);
        });
    }

    private static function saveLocked(string $section, array $input): void
    {
        if ($section === 'sms_default') {
            $provider = strtolower(trim((string)($input['value'] ?? '')));
            if (!in_array($provider, self::PROVIDERS, true)
                || !self::complete($provider, self::config($provider))) {
                throw new \RuntimeException('只能选择已启用且配置完整的短信服务商');
            }
            ConfigService::set('notice', 'sms_default', $provider);
            return;
        }

        $provider = str_starts_with($section, 'sms_') ? substr($section, 4) : '';
        if (!in_array($provider, self::PROVIDERS, true)) {
            throw new \RuntimeException('无效的短信配置节');
        }

        $allowed = $provider === 'aliyun'
            ? ['access_key_id', 'access_key_secret', 'sign_name', 'status']
            : ['secret_id', 'secret_key', 'sdk_app_id', 'sign_name', 'region', 'status'];
        if (array_diff(array_keys($input), $allowed) !== []) {
            throw new \RuntimeException('短信配置包含未知字段');
        }

        $current = self::config($provider);
        $config = [];
        foreach ($allowed as $field) {
            if ($field === 'status') {
                $config[$field] = (int)($input[$field] ?? $current[$field] ?? 0);
                continue;
            }
            $value = trim((string)($input[$field] ?? $current[$field] ?? ''));
            $config[$field] = $value === '******' ? (string)($current[$field] ?? '') : $value;
        }
        if (!in_array($config['status'], [0, 1], true)) {
            throw new \RuntimeException('短信服务状态无效');
        }
        if ($config['status'] === 1 && !self::complete($provider, $config)) {
            throw new \RuntimeException('启用短信服务前请完整填写服务商配置');
        }

        $changes = [$section => $config];
        if ($config['status'] === 1) {
            $other = $provider === 'aliyun' ? 'tencent' : 'aliyun';
            $otherConfig = self::config($other);
            $otherConfig['status'] = 0;
            $changes['sms_' . $other] = $otherConfig;
            $changes['sms_default'] = $provider;
        } elseif ((string)ConfigService::get('notice', 'sms_default', '') === $provider) {
            $changes['sms_default'] = '';
        }
        ConfigService::setMany('notice', $changes);
    }

    /** @return array{success:bool,provider:string,error:string,result:array<string,mixed>} */
    public static function sendSms(
        string $mobile,
        string $templateId,
        array $variables,
        ?callable $beforeSend = null
    ): array
    {
        $provider = strtolower(trim((string)ConfigService::get('notice', 'sms_default', '')));
        $config = in_array($provider, self::PROVIDERS, true) ? self::config($provider) : [];
        if (!self::complete($provider, $config)) {
            return self::result(false, $provider, '短信服务商未启用或配置不完整');
        }

        $driver = self::makeDriver($provider, $config);
        if ($beforeSend !== null) {
            $beforeSend($provider);
        }
        try {
            $success = $driver->send($mobile, $templateId, $variables);
            $error = $success ? '' : $driver->getError();
            return self::result(
                $success,
                $provider,
                self::sanitizeError($error, $mobile, $config),
                self::safeReceipt($provider, $driver->getResult())
            );
        } catch (\Throwable $exception) {
            return self::result(
                false,
                $provider,
                self::sanitizeError($exception->getMessage(), $mobile, $config)
            );
        }
    }

    /** @return array{success:bool,provider:string,error:string,result:array<string,mixed>} */
    private static function result(
        bool $success,
        string $provider,
        string $error,
        array $result = []
    ): array {
        return compact('success', 'provider', 'error', 'result');
    }

    private static function config(string $provider): array
    {
        $raw = ConfigService::get('notice', 'sms_' . $provider, '');
        return is_array($raw) ? $raw : (json_decode((string)$raw, true) ?? []);
    }

    private static function complete(string $provider, array $config): bool
    {
        if ((int)($config['status'] ?? 0) !== 1) {
            return false;
        }
        $required = $provider === 'aliyun'
            ? ['access_key_id', 'access_key_secret', 'sign_name']
            : ($provider === 'tencent'
                ? ['secret_id', 'secret_key', 'sdk_app_id', 'sign_name', 'region'] : []);
        if ($required === []) {
            return false;
        }
        foreach ($required as $field) {
            if (trim((string)($config[$field] ?? '')) === '') {
                return false;
            }
        }
        return true;
    }

    private static function makeDriver(string $provider, array $config): SmsDriver
    {
        return match ($provider) {
            'aliyun' => new AliyunSms($config),
            'tencent' => new TencentSms($config),
            default => throw new \RuntimeException('短信服务商不受支持'),
        };
    }

    private static function sanitizeError(string $error, string $mobile, array $config): string
    {
        $secrets = [$mobile];
        foreach (['access_key_secret', 'secret_key'] as $field) {
            if (trim((string)($config[$field] ?? '')) !== '') {
                $secrets[] = (string)$config[$field];
            }
        }
        return mb_substr(str_replace($secrets, '[redacted]', $error), 0, 500);
    }

    private static function safeReceipt(string $provider, array $result): array
    {
        if ($provider === 'aliyun') {
            return array_intersect_key($result, array_flip(['Code', 'Message', 'RequestId', 'BizId']));
        }
        $response = is_array($result['Response'] ?? null) ? $result['Response'] : [];
        $receipt = ['RequestId' => (string)($response['RequestId'] ?? '')];
        $receipt['SendStatusSet'] = array_map(
            static fn(array $status): array => array_intersect_key(
                $status,
                array_flip(['Code', 'Message', 'SerialNo'])
            ),
            is_array($response['SendStatusSet'] ?? null) ? $response['SendStatusSet'] : []
        );
        return $receipt;
    }
}
