<?php
declare(strict_types=1);

namespace app\Modules\Official\Oauth\Application;

use app\common\service\external\ExternalTenantBinding;
use app\common\service\external\ExternalTenantResolver;
use think\facade\Db;

/** OAuth owns state/ticket lookup; other Modules consume only this narrow result. */
final class OAuthCallbackLocator
{
    /** @return list<ExternalTenantBinding> */
    public static function byState(string $provider, string $stateHash): array
    {
        return self::bindings(
            Db::name('oauth_attempt')->alias('o')
                ->field(self::bindingFields())
                ->join('external_channel_binding b', 'b.tenant_id = o.tenant_id')
                ->join('tenant t', 't.id = b.tenant_id')
                ->where('b.provider', $provider)
                ->where('o.state_hash', $stateHash)
                ->whereNull('o.used_at')
                ->where('o.expires_at', '>=', time())
                ->limit(2)->select()->toArray()
        );
    }

    /** @return list<ExternalTenantBinding> */
    public static function byTicket(string $ticketHash): array
    {
        return self::bindings(
            Db::name('oauth_completion_ticket')->alias('o')
                ->field(self::bindingFields())
                ->join('external_channel_binding b', 'b.id = o.binding_id AND b.tenant_id = o.tenant_id')
                ->join('tenant t', 't.id = b.tenant_id')
                ->where('o.token_hash', $ticketHash)
                ->whereNull('o.used_at')
                ->where('o.expires_at', '>=', time())
                ->whereIn('b.provider', [
                    ExternalTenantResolver::WECHAT_MINI_PROGRAM,
                    ExternalTenantResolver::WECHAT_OFFICIAL_OAUTH,
                    ExternalTenantResolver::WECHAT_OPEN_PLATFORM,
                ])
                ->limit(2)->select()->toArray()
        );
    }

    /** @param list<array<string, mixed>> $rows @return list<ExternalTenantBinding> */
    private static function bindings(array $rows): array
    {
        return array_map(static function (array $row): ExternalTenantBinding {
            $config = json_decode((string)($row['config_json'] ?? ''), true);
            return new ExternalTenantBinding(
                (int)($row['id'] ?? 0),
                (int)($row['tenant_id'] ?? 0),
                (string)($row['provider'] ?? ''),
                (string)($row['callback_key'] ?? ''),
                (string)($row['identity_hash'] ?? ''),
                (string)($row['identity_hint'] ?? ''),
                is_array($config) ? $config : [],
                (int)($row['status'] ?? 0) === 1,
                (string)($row['tenant_status'] ?? '') === 'active',
            );
        }, $rows);
    }

    private static function bindingFields(): string
    {
        return 'b.id,b.tenant_id,b.provider,b.callback_key,b.identity_hash,b.identity_hint,'
            . 'b.config_json,b.status,t.status AS tenant_status';
    }
}
