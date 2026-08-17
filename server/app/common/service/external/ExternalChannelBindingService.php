<?php
declare(strict_types=1);

namespace app\common\service\external;

use PeanutAdmin\Kernel\Auth\TenantContext;
use think\facade\Db;

final class ExternalChannelBindingService
{
    public static function config(TenantContext $context, string $provider): array
    {
        $binding = self::optionalBinding($context, $provider);
        return $binding?->config ?? [];
    }

    public static function callbackKey(TenantContext $context, string $provider): string
    {
        return ExternalTenantResolver::production()->bindingForTenant($context, $provider)->callbackKey;
    }

    public static function update(TenantContext $context, string $provider, array $config, string $identity): void
    {
        $tenantId = ExternalTenantContext::tenantId($context);
        $identity = strtolower(trim($identity));
        $enabled = self::enabled($provider, $config);
        if (trim($provider) === '' || strlen($provider) > 64
            || ($enabled && $identity === '') || strlen($identity) > 191) {
            throw new \RuntimeException('外部渠道身份不能为空');
        }
        $encoded = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        Db::transaction(function () use ($tenantId, $provider, $identity, $enabled, $encoded): void {
            self::assertActiveTenant($tenantId, true);
            $binding = Db::name('external_channel_binding')
                ->where('tenant_id', $tenantId)
                ->where('provider', $provider)
                ->lock(true)
                ->find();
            $currentKey = is_array($binding) ? (string)($binding['callback_key'] ?? '') : '';
            $callbackKey = self::callbackKeyForUpdate($provider, $currentKey, $enabled);
            $now = time();

            if (!is_array($binding)) {
                Db::name('external_channel_binding')->insert([
                    'tenant_id' => $tenantId,
                    'provider' => $provider,
                    'callback_key' => $callbackKey,
                    'identity_hash' => $identity !== ''
                        ? hash('sha256', $identity)
                        : hash('sha256', 'unconfigured:' . $provider . ':' . $tenantId),
                    'identity_hint' => $identity !== '' ? substr($identity, -8) : '',
                    'config_json' => $encoded,
                    'status' => $enabled ? 1 : 0,
                    'create_time' => $now,
                    'update_time' => $now,
                ]);
                return;
            }

            $update = [
                'callback_key' => $callbackKey,
                'config_json' => $encoded,
                'status' => $enabled ? 1 : 0,
                'update_time' => $now,
            ];
            if ($identity !== '') {
                $update['identity_hash'] = hash('sha256', $identity);
                $update['identity_hint'] = substr($identity, -8);
            }
            Db::name('external_channel_binding')
                ->where('id', (int)$binding['id'])
                ->where('tenant_id', $tenantId)
                ->update($update);
        });
    }

    private static function optionalBinding(TenantContext $context, string $provider): ?ExternalTenantBinding
    {
        $tenantId = ExternalTenantContext::tenantId($context);
        $bindings = (new ThinkPhpExternalTenantBindingRepository())->byTenant($provider, $tenantId);
        if ($bindings === []) {
            self::assertActiveTenant($tenantId);
            return null;
        }
        return ExternalTenantResolver::production()->bindingForTenant($context, $provider, false);
    }

    private static function assertActiveTenant(int $tenantId, bool $lock = false): void
    {
        $query = Db::name('tenant')->where('id', $tenantId);
        if ($lock) {
            $query->lock(true);
        }
        if ((string)$query->value('status') !== 'active') {
            throw new ExternalTenantResolutionException();
        }
    }

    private static function callbackKeyForUpdate(string $provider, string $current, bool $enabled): string
    {
        $freshPlaceholder = hash('sha256', 'fresh-default:' . $provider);
        if ($current === '' || ($enabled && hash_equals($freshPlaceholder, $current))) {
            return bin2hex(random_bytes(32));
        }
        return $current;
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
