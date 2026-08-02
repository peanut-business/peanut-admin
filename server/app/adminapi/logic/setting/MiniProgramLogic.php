<?php
declare(strict_types=1);

namespace app\adminapi\logic\setting;

use app\common\logic\BaseLogic;
use app\common\service\ConfigService;
use app\common\service\FileService;

/** 微信小程序基础配置。 */
class MiniProgramLogic extends BaseLogic
{
    protected const CONFIG_TYPE = 'mnp_setting';

    public static function getConfig(): array
    {
        $qrCode = (string) ConfigService::get(self::CONFIG_TYPE, 'qr_code', '');
        $secret = (string)ConfigService::get(self::CONFIG_TYPE, 'app_secret', '');
        $domains = self::domainConfig();

        return [
            'name'                 => (string) ConfigService::get(self::CONFIG_TYPE, 'name', ''),
            'original_id'          => (string) ConfigService::get(self::CONFIG_TYPE, 'original_id', ''),
            'qr_code'              => FileService::getFileUrl($qrCode),
            'app_id'               => (string) ConfigService::get(self::CONFIG_TYPE, 'app_id', ''),
            'app_secret'           => $secret !== '' ? '******' : '',
            'app_secret_configured'=> $secret !== '',
            'request_domain'       => $domains['https'],
            'socket_domain'        => $domains['wss'],
            'upload_file_domain'   => $domains['https'],
            'download_file_domain' => $domains['https'],
            'udp_domain'           => $domains['udp'],
            'business_domain'      => $domains['authority'],
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
            'name'        => trim((string) ($params['name'] ?? '')),
            'original_id' => trim((string) ($params['original_id'] ?? '')),
            'qr_code'     => self::relativeQrCode((string) ($params['qr_code'] ?? '')),
            'app_id'      => trim((string) $params['app_id']),
            'app_secret'  => $secret,
        ]);
        return true;
    }

    /** @return array{https:string,wss:string,udp:string,authority:string} */
    private static function domainConfig(): array
    {
        $domain = rtrim((string) request()->domain(), '/');
        $parts = parse_url($domain);
        $host = is_array($parts) ? (string) ($parts['host'] ?? '') : '';

        if ($host === '') {
            $authority = preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', $domain) ?: '';
            $authority = trim($authority, '/');
        } else {
            if (str_contains($host, ':') && !str_starts_with($host, '[')) {
                $host = '[' . $host . ']';
            }
            $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
            $authority = $host . $port;
        }

        return [
            'https'     => 'https://' . $authority,
            'wss'       => 'wss://' . $authority,
            'udp'       => 'udp://' . $authority,
            'authority' => $authority,
        ];
    }

    private static function relativeQrCode(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $uri = FileService::setFileUrl($value);
        if (preg_match('#^https?://#i', $uri)) {
            $uri = (string) parse_url($uri, PHP_URL_PATH);
        }
        return ltrim($uri, '/');
    }
}
