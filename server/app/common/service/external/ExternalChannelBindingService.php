<?php
declare(strict_types=1);

namespace app\common\service\external;

use PeanutAdmin\Kernel\Auth\TenantContext;
use think\facade\Db;

final class ExternalChannelBindingService
{
    public static function config(TenantContext $context, string $provider): array
    {
        return ExternalTenantResolver::production()->bindingForTenant($context, $provider, false)->config;
    }

    public static function callbackKey(TenantContext $context, string $provider): string
    {
        return ExternalTenantResolver::production()->bindingForTenant($context, $provider)->callbackKey;
    }

    public static function update(TenantContext $context, string $provider, array $config, string $identity): void
    {
        $binding = ExternalTenantResolver::production()->bindingForTenant($context, $provider, false);
        $identity = strtolower(trim($identity));
        $enabled = self::enabled($provider, $config);
        if (($enabled && $identity === '') || strlen($identity) > 191) {
            throw new \RuntimeException('外部渠道身份不能为空');
        }
        $encoded = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $update = [
            'config_json' => $encoded,
            'status' => $enabled ? 1 : 0,
            'update_time' => time(),
        ];
        if ($identity !== '') {
            $update['identity_hash'] = hash('sha256', $identity);
            $update['identity_hint'] = substr($identity, -8);
        }
        Db::name('external_channel_binding')->where('id', $binding->id)->update($update);
    }

    private static function enabled(string $provider, array $config): bool
    {
        return match ($provider) {
            ExternalTenantResolver::WECHAT_PAYMENT => (int)($config['wx_pay_status'] ?? 0) === 1,
            ExternalTenantResolver::ALIPAY_PAYMENT => (int)($config['ali_pay_status'] ?? 0) === 1,
            ExternalTenantResolver::WECHAT_OFFICIAL_CALLBACK => trim((string)($config['token'] ?? '')) !== '',
            default => trim((string)($config['app_id'] ?? '')) !== ''
                && trim((string)($config['app_secret'] ?? '')) !== '',
        };
    }
}
