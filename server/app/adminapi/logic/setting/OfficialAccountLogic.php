<?php
declare(strict_types=1);

namespace app\adminapi\logic\setting;

use app\common\logic\BaseLogic;
use app\common\service\ConfigService;
use app\common\service\FileService;

class OfficialAccountLogic extends BaseLogic
{
    private const CONFIG_TYPE = 'oa_setting';

    public static function getConfig(): array
    {
        $qrCode = (string)ConfigService::get(self::CONFIG_TYPE, 'qr_code', '');
        $secret = (string)ConfigService::get(self::CONFIG_TYPE, 'app_secret', '');
        $domain = rtrim((string)request()->domain(), '/');
        $authority = self::authority($domain);

        return [
            'name' => (string)ConfigService::get(self::CONFIG_TYPE, 'name', ''),
            'original_id' => (string)ConfigService::get(self::CONFIG_TYPE, 'original_id', ''),
            'qr_code' => FileService::getFileUrl($qrCode),
            'app_id' => (string)ConfigService::get(self::CONFIG_TYPE, 'app_id', ''),
            'app_secret' => $secret !== '' ? '******' : '',
            'app_secret_configured' => $secret !== '',
            'url' => $domain . '/api/wechat/official-account/callback',
            'token' => (string)ConfigService::get(self::CONFIG_TYPE, 'token', ''),
            'encoding_aes_key' => (string)ConfigService::get(self::CONFIG_TYPE, 'encoding_aes_key', ''),
            'encryption_type' => (int)ConfigService::get(self::CONFIG_TYPE, 'encryption_type', 1),
            'business_domain' => $authority,
            'js_secure_domain' => $authority,
            'web_auth_domain' => $authority,
            'callback_mode' => 'plaintext',
        ];
    }

    public static function setConfig(array $params): bool
    {
        $currentSecret = (string)ConfigService::get(self::CONFIG_TYPE, 'app_secret', '');
        $incomingSecret = trim((string)$params['app_secret']);
        $secret = $incomingSecret === '******' ? $currentSecret : $incomingSecret;
        if ($secret === '') {
            self::setError('AppSecret 不能为空');
            return false;
        }
        ConfigService::setManyAtomic(self::CONFIG_TYPE, [
            'name' => trim((string)($params['name'] ?? '')),
            'original_id' => trim((string)($params['original_id'] ?? '')),
            'qr_code' => self::relativeFile((string)($params['qr_code'] ?? '')),
            'app_id' => trim((string)$params['app_id']),
            'app_secret' => $secret,
            'token' => trim((string)($params['token'] ?? '')),
            'encoding_aes_key' => trim((string)($params['encoding_aes_key'] ?? '')),
            'encryption_type' => (int)$params['encryption_type'],
        ]);
        return true;
    }

    private static function relativeFile(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $uri = FileService::setFileUrl($value);
        if (preg_match('#^https?://#i', $uri)) {
            $uri = (string)parse_url($uri, PHP_URL_PATH);
        }
        return ltrim($uri, '/');
    }

    private static function authority(string $domain): string
    {
        $parts = parse_url($domain);
        if (!is_array($parts) || empty($parts['host'])) {
            return trim((string)preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', $domain), '/');
        }
        $host = (string)$parts['host'];
        if (str_contains($host, ':') && !str_starts_with($host, '[')) {
            $host = '[' . $host . ']';
        }
        return $host . (isset($parts['port']) ? ':' . (int)$parts['port'] : '');
    }
}
