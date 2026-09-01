<?php
declare(strict_types=1);

namespace app\Modules\Official\Oauth\Application;

use app\common\application\BusinessException;
use app\common\service\FileService;
use app\common\service\external\ExternalChannelBindingService;
use app\common\service\external\ExternalTenantResolver;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Persistence\TransactionManager;

class OfficialAccountApplicationService
{
    private const CONFIG_TYPE = 'oa_setting';

    public function __construct(private readonly TransactionManager $transactions)
    {
    }

    public function getConfig(TenantContext $context): array
    {
        $stored = ExternalChannelBindingService::config($context, ExternalTenantResolver::WECHAT_OFFICIAL_CALLBACK);
        $qrCode = (string)($stored['qr_code'] ?? '');
        $secret = (string)($stored['app_secret'] ?? '');
        $domain = rtrim((string)request()->domain(), '/');
        $authority = self::authority($domain);

        return [
            'name' => (string)($stored['name'] ?? ''),
            'original_id' => (string)($stored['original_id'] ?? ''),
            'qr_code' => FileService::getFileUrl($qrCode),
            'app_id' => (string)($stored['app_id'] ?? ''),
            'app_secret' => $secret !== '' ? '******' : '',
            'app_secret_configured' => $secret !== '',
            'url' => $domain . '/api/wechat/official-account/callback/'
                . ExternalTenantResolver::production()->bindingForTenant(
                    $context,
                    ExternalTenantResolver::WECHAT_OFFICIAL_CALLBACK,
                    false,
                )->callbackKey,
            'token' => (string)($stored['token'] ?? ''),
            'business_domain' => $authority,
            'js_secure_domain' => $authority,
            'web_auth_domain' => $authority,
            'callback_mode' => 'plaintext',
        ];
    }

    public function setConfig(TenantContext $context, array $params): bool
    {
        $current = ExternalChannelBindingService::config($context, ExternalTenantResolver::WECHAT_OFFICIAL_CALLBACK);
        $currentSecret = (string)($current['app_secret'] ?? '');
        $incomingSecret = trim((string)$params['app_secret']);
        $secret = $incomingSecret === '******' ? $currentSecret : $incomingSecret;
        if ($secret === '') {
            throw BusinessException::invalid('OAUTH_APP_SECRET_REQUIRED', 'AppSecret 不能为空');
        }
        $data = [
            'name' => trim((string)($params['name'] ?? '')),
            'original_id' => trim((string)($params['original_id'] ?? '')),
            'qr_code' => self::relativeFile($context, (string)($params['qr_code'] ?? '')),
            'app_id' => trim((string)$params['app_id']),
            'app_secret' => $secret,
            'token' => trim((string)($params['token'] ?? '')),
        ];
        $this->transactions->run(function () use ($context, $data): void {
            ExternalChannelBindingService::update(
                $context,
                ExternalTenantResolver::WECHAT_OFFICIAL_CALLBACK,
                $data,
                $data['original_id'] !== '' ? $data['original_id'] : $data['app_id'],
            );
            ExternalChannelBindingService::update(
                $context,
                ExternalTenantResolver::WECHAT_OFFICIAL_OAUTH,
                ['app_id' => $data['app_id'], 'app_secret' => $data['app_secret']],
                $data['app_id'],
            );
        });
        return true;
    }

    private static function relativeFile(TenantContext $context, string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $uri = FileService::setTenantFileUrl($context, $value);
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
