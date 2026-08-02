<?php
declare(strict_types=1);

namespace app\common\service\oauth;

use app\common\service\oauth\contract\OAuthTransportInterface;
use app\common\service\oauth\dto\OAuthProfile;

/** 微信小程序、公众号和开放平台 PC 的生产 OAuth 传输。 */
final class WechatOAuthTransport implements OAuthTransportInterface
{
    public function authorizationUrl(
        string $scene,
        array $config,
        string $redirectUri,
        string $state
    ): string {
        $appId = trim((string)($config['app_id'] ?? ''));
        if ($appId === '' || $redirectUri === '' || $state === '') {
            throw new \RuntimeException('微信授权参数不完整');
        }
        $params = [
            'appid' => $appId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => $scene === 'oa' ? 'snsapi_userinfo' : 'snsapi_login',
            'state' => $state,
        ];
        $base = match ($scene) {
            'oa' => 'https://open.weixin.qq.com/connect/oauth2/authorize',
            'open_pc' => 'https://open.weixin.qq.com/connect/qrconnect',
            default => throw new \RuntimeException('该微信场景不需要浏览器授权地址'),
        };
        return $base . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986) . '#wechat_redirect';
    }

    public function exchange(string $scene, array $config, string $code): OAuthProfile
    {
        $appId = trim((string)($config['app_id'] ?? ''));
        $secret = trim((string)($config['app_secret'] ?? ''));
        $code = trim($code);
        if ($appId === '' || $secret === '' || $code === '') {
            throw new \RuntimeException('微信授权配置或 code 缺失');
        }

        if ($scene === 'mnp') {
            $data = $this->getJson('https://api.weixin.qq.com/sns/jscode2session?' . http_build_query([
                'appid' => $appId,
                'secret' => $secret,
                'js_code' => $code,
                'grant_type' => 'authorization_code',
            ], '', '&', PHP_QUERY_RFC3986));
            return new OAuthProfile(
                (string)($data['openid'] ?? ''),
                (string)($data['unionid'] ?? '')
            );
        }
        if (!in_array($scene, ['oa', 'open_pc'], true)) {
            throw new \RuntimeException('微信授权场景不支持');
        }

        $token = $this->getJson('https://api.weixin.qq.com/sns/oauth2/access_token?' . http_build_query([
            'appid' => $appId,
            'secret' => $secret,
            'code' => $code,
            'grant_type' => 'authorization_code',
        ], '', '&', PHP_QUERY_RFC3986));
        $accessToken = trim((string)($token['access_token'] ?? ''));
        $openid = trim((string)($token['openid'] ?? ''));
        if ($accessToken === '' || $openid === '') {
            throw new \RuntimeException('微信 code 换取身份失败');
        }
        $profile = $this->getJson('https://api.weixin.qq.com/sns/userinfo?' . http_build_query([
            'access_token' => $accessToken,
            'openid' => $openid,
            'lang' => 'zh_CN',
        ], '', '&', PHP_QUERY_RFC3986));
        return new OAuthProfile(
            $openid,
            (string)($profile['unionid'] ?? $token['unionid'] ?? ''),
            (string)($profile['nickname'] ?? ''),
            (string)($profile['headimgurl'] ?? '')
        );
    }

    private function getJson(string $url): array
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('服务器未安装 cURL 扩展，无法调用微信授权');
        }
        $curl = curl_init($url);
        if ($curl === false) {
            throw new \RuntimeException('微信授权请求初始化失败');
        }
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $body = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if ($body === false || $status < 200 || $status >= 300) {
            throw new \RuntimeException('微信授权网络异常' . ($error !== '' ? ':' . $error : ''));
        }
        $data = json_decode((string)$body, true);
        if (!is_array($data)) {
            throw new \RuntimeException('微信授权响应格式异常');
        }
        if (isset($data['errcode']) && (int)$data['errcode'] !== 0) {
            throw new \RuntimeException('微信授权失败:' . (string)($data['errmsg'] ?? $data['errcode']));
        }
        return $data;
    }
}
