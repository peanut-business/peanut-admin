<?php
declare(strict_types=1);

namespace app\adminapi\logic\setting;

use app\common\logic\BaseLogic;
use app\common\service\config\TenantApplicationSettingService;
use app\common\service\member\AuthenticatedMemberContext;
use PeanutAdmin\Kernel\Auth\TenantContext;

/** H5 网页渠道配置。 */
class WebPageLogic extends BaseLogic
{
    protected const CONFIG_TYPE = 'web_page';

    public static function getConfig(AuthenticatedMemberContext|TenantContext $context): array
    {
        $setting = TenantApplicationSettingService::webPage($context);
        return [
            'status'      => (int)$setting['status'],
            'page_status' => (int)$setting['page_status'],
            'page_url'    => (string)$setting['page_url'],
            'url'         => rtrim(request()->domain(), '/') . '/mobile',
        ];
    }

    public static function setConfig(AuthenticatedMemberContext|TenantContext $context, array $params): bool
    {
        TenantApplicationSettingService::replaceWebPage($context, [
            'status'      => (int) $params['status'],
            'page_status' => (int) $params['page_status'],
            'page_url'    => trim((string) ($params['page_url'] ?? '')),
        ]);
        return true;
    }
}
