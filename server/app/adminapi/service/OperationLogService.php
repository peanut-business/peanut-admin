<?php
declare(strict_types=1);

namespace app\adminapi\service;

use app\common\service\audit\OperationLogTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;

/** 管理端操作日志的唯一写入与脱敏入口。 */
final class OperationLogService
{
    private const REDACTED = '******';
    private const MAX_PARAMS_BYTES = 60000;

    /** 会出现在组合字段名中的敏感片段。 */
    private const SENSITIVE_FRAGMENTS = [
        'password', 'passwd', 'salt', 'token', 'secret', 'privatekey',
        'apikey', 'accesskey', 'aeskey', 'certificate', 'cert', 'mchkey',
        'apiv3key', 'signkey', 'encryptionkey', 'decryptionkey',
        'authorization', 'cookie', 'ticket',
    ];

    /** 仅在字段名完全匹配时脱敏，避免误伤 jobs_code 等业务编码。 */
    private const SENSITIVE_EXACT_KEYS = [
        'code', 'verificationcode', 'smscode', 'captchacode', 'captcha',
    ];

    public static function record(
        TenantContext $context,
        int $adminId,
        string $username,
        string $ip,
        string $uri,
        string $method,
        mixed $params
    ): void {
        OperationLogTenantRepository::create($context, [
            'admin_id' => $adminId,
            'username' => $username,
            'ip' => $ip,
            'uri' => strtolower(trim($uri, '/')),
            'method' => strtoupper($method),
            'params' => self::serializeParams($params),
        ]);
    }

    public static function serializeParams(mixed $params): string
    {
        $encoded = json_encode(
            self::redactSensitive($params),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        if (!is_string($encoded) || strlen($encoded) > self::MAX_PARAMS_BYTES) {
            return '{"_redacted":"payload_unavailable"}';
        }
        return $encoded;
    }

    public static function redactSensitive(mixed $value, string $key = ''): mixed
    {
        $normalized = strtolower(preg_replace('/[^a-z0-9]/i', '', $key) ?: '');
        if (in_array($normalized, self::SENSITIVE_EXACT_KEYS, true)) {
            return self::REDACTED;
        }
        foreach (self::SENSITIVE_FRAGMENTS as $fragment) {
            if ($normalized !== '' && str_contains($normalized, $fragment)) {
                return self::REDACTED;
            }
        }
        if (!is_array($value)) {
            return $value;
        }
        foreach ($value as $childKey => $childValue) {
            $value[$childKey] = self::redactSensitive($childValue, (string)$childKey);
        }
        return $value;
    }
}
