<?php
declare(strict_types=1);

namespace app\common\service\oauth;

/** Browser callback bridge between WeChat and the product-specific PC/H5 routes. */
final class OAuthBrowserCallbackService
{
    private const CALLBACK_PATHS = [
        'oa' => '/api/oauth/wechat/redirect/official-account',
        'open_pc' => '/api/oauth/wechat/redirect/pc',
    ];

    private const CLIENT_PATHS = [
        'official-account' => '/mobile/#/pages/oauth/callback',
        'pc' => '/pc/oauth/callback',
    ];

    private const QUERY_FIELDS = ['code', 'state', 'error', 'error_description'];

    public static function callbackUrl(string $origin, string $scene): string
    {
        if (!isset(self::CALLBACK_PATHS[$scene])) {
            throw new \InvalidArgumentException('微信浏览器授权场景无效');
        }
        return rtrim($origin, '/') . self::CALLBACK_PATHS[$scene];
    }

    public static function clientRedirectUrl(string $client, array $query): string
    {
        if (!isset(self::CLIENT_PATHS[$client])) {
            throw new \InvalidArgumentException('微信授权回跳客户端无效');
        }

        $safeQuery = $client === 'official-account' ? ['scene' => 'oa'] : [];
        foreach (self::QUERY_FIELDS as $field) {
            $raw = $query[$field] ?? '';
            if (!is_scalar($raw)) {
                continue;
            }
            $value = trim((string)$raw);
            if ($value !== '') {
                $safeQuery[$field] = $value;
            }
        }
        $suffix = $safeQuery === []
            ? ''
            : '?' . http_build_query($safeQuery, '', '&', PHP_QUERY_RFC3986);
        return self::CLIENT_PATHS[$client] . $suffix;
    }
}
