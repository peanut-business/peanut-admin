<?php
declare(strict_types=1);

namespace app\adminapi\logic\setting;

use app\common\logic\BaseLogic;
use app\common\service\ConfigService;

class OpenPlatformLogic extends BaseLogic
{
    private const CONFIG_TYPE = 'open_platform';

    public static function getConfig(): array
    {
        $secret = (string)ConfigService::get(self::CONFIG_TYPE, 'app_secret', '');
        return [
            'app_id' => (string)ConfigService::get(self::CONFIG_TYPE, 'app_id', ''),
            'app_secret' => $secret !== '' ? '******' : '',
            'app_secret_configured' => $secret !== '',
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
            'app_id' => trim((string)$params['app_id']),
            'app_secret' => $secret,
        ]);
        return true;
    }
}
