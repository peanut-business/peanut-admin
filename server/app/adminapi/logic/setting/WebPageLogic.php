<?php
declare(strict_types=1);

namespace app\adminapi\logic\setting;

use app\common\logic\BaseLogic;
use app\common\service\ConfigService;

/** H5 网页渠道配置。 */
class WebPageLogic extends BaseLogic
{
    protected const CONFIG_TYPE = 'web_page';

    public static function getConfig(): array
    {
        return [
            'status'      => (int) ConfigService::get(self::CONFIG_TYPE, 'status', 1),
            'page_status' => (int) ConfigService::get(self::CONFIG_TYPE, 'page_status', 0),
            'page_url'    => (string) ConfigService::get(self::CONFIG_TYPE, 'page_url', ''),
            'url'         => rtrim(request()->domain(), '/') . '/mobile',
        ];
    }

    public static function setConfig(array $params): bool
    {
        ConfigService::setManyAtomic(self::CONFIG_TYPE, [
            'status'      => (int) $params['status'],
            'page_status' => (int) $params['page_status'],
            'page_url'    => trim((string) ($params['page_url'] ?? '')),
        ]);
        return true;
    }
}
