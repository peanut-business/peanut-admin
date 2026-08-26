<?php
declare(strict_types=1);

namespace app\Modules\Official\Oauth\Service;

use app\common\logic\BaseLogic;
use app\common\service\external\ExternalChannelBindingService;
use app\common\service\external\ExternalTenantResolver;
use app\common\service\wechat\OfficialAccountService;
use PeanutAdmin\Kernel\Auth\TenantContext;

class OfficialAccountMenuLogic extends BaseLogic
{
    public static function detail(TenantContext $context): array
    {
        self::clearError();
        $stored = self::config($context);
        $menu = $stored['menu'] ?? [];
        return ['menu' => is_array($menu) ? $menu : []];
    }

    public static function save(TenantContext $context, array $menu): bool
    {
        self::clearError();
        try {
            self::store($context, $menu);
            return true;
        } catch (\Throwable $e) {
            return self::fail($e);
        }
    }

    public static function saveAndPublish(
        TenantContext $context,
        array $menu,
        ?OfficialAccountService $service = null
    ): bool
    {
        self::clearError();
        try {
            $config = self::config($context);
            $service ??= new OfficialAccountService();
            $service->publishMenu(
                (string)($config['app_id'] ?? ''),
                (string)($config['app_secret'] ?? ''),
                $menu
            );
            self::store($context, $menu, $config);
            return true;
        } catch (\Throwable $e) {
            return self::fail($e);
        }
    }

    private static function store(TenantContext $context, array $menu, ?array $config = null): void
    {
        $config ??= self::config($context);
        $config['menu'] = $menu;
        ExternalChannelBindingService::update(
            $context,
            ExternalTenantResolver::WECHAT_OFFICIAL_CALLBACK,
            $config,
            (string)($config['original_id'] ?? $config['app_id'] ?? '')
        );
    }

    private static function config(TenantContext $context): array
    {
        return ExternalChannelBindingService::config(
            $context,
            ExternalTenantResolver::WECHAT_OFFICIAL_CALLBACK
        );
    }
}
