<?php
declare(strict_types=1);

namespace app\Modules\Official\Oauth\Application;

use app\common\application\ApplicationService;
use app\common\service\external\ExternalChannelBindingService;
use app\common\service\external\ExternalTenantResolver;
use PeanutAdmin\Kernel\Auth\TenantContext;

class OpenPlatformApplicationService extends ApplicationService
{
    private const CONFIG_TYPE = 'open_platform';

    public function getConfig(TenantContext $context): array
    {
        $stored = ExternalChannelBindingService::config($context, ExternalTenantResolver::WECHAT_OPEN_PLATFORM);
        $secret = (string)($stored['app_secret'] ?? '');
        return [
            'app_id' => (string)($stored['app_id'] ?? ''),
            'app_secret' => $secret !== '' ? '******' : '',
            'app_secret_configured' => $secret !== '',
        ];
    }

    public function setConfig(TenantContext $context, array $params): bool
    {
        $current = ExternalChannelBindingService::config($context, ExternalTenantResolver::WECHAT_OPEN_PLATFORM);
        $currentSecret = (string)($current['app_secret'] ?? '');
        $incomingSecret = trim((string)$params['app_secret']);
        $secret = $incomingSecret === '******' ? $currentSecret : $incomingSecret;
        if ($secret === '') {
            self::setError('AppSecret 不能为空');
            return false;
        }
        $data = [
            'app_id' => trim((string)$params['app_id']),
            'app_secret' => $secret,
        ];
        ExternalChannelBindingService::update(
            $context,
            ExternalTenantResolver::WECHAT_OPEN_PLATFORM,
            $data,
            $data['app_id'],
        );
        return true;
    }
}
