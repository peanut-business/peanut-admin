<?php
declare(strict_types=1);

namespace app\common\service\external;

use think\facade\Db;

final class ThinkPhpExternalTenantBindingRepository implements ExternalTenantBindingRepository
{
    public function byCallbackKey(string $provider, string $callbackKey): array
    {
        return $this->bindings(
            Db::name('external_channel_binding')->alias('b')
                ->field($this->bindingFields())
                ->join('tenant t', 't.id = b.tenant_id')
                ->where('b.provider', $provider)
                ->where('b.callback_key', $callbackKey)
                ->limit(2)->select()->toArray()
        );
    }

    public function byClientIdentity(string $provider, string $identityHash): array
    {
        return $this->bindings(
            Db::name('external_channel_binding')->alias('b')
                ->field($this->bindingFields())
                ->join('tenant t', 't.id = b.tenant_id')
                ->where('b.provider', $provider)
                ->where('b.identity_hash', $identityHash)
                ->limit(2)->select()->toArray()
        );
    }

    public function byProvider(string $provider): array
    {
        return $this->bindings(
            Db::name('external_channel_binding')->alias('b')
                ->field($this->bindingFields())
                ->join('tenant t', 't.id = b.tenant_id')
                ->where('b.provider', $provider)
                ->limit(2)->select()->toArray()
        );
    }

    public function byTenant(string $provider, int $tenantId): array
    {
        return $this->bindings(
            Db::name('external_channel_binding')->alias('b')
                ->field($this->bindingFields())
                ->join('tenant t', 't.id = b.tenant_id')
                ->where('b.provider', $provider)
                ->where('b.tenant_id', $tenantId)
                ->limit(2)->select()->toArray()
        );
    }

    public function byOAuthState(string $provider, string $stateHash): array
    {
        return (new \app\Modules\Official\Oauth\ModuleProvider())->commands()->locateState($provider, $stateHash);
    }

    public function byOAuthTicket(string $ticketHash): array
    {
        return (new \app\Modules\Official\Oauth\ModuleProvider())->commands()->locateTicket($ticketHash);
    }

    /** @param list<array<string, mixed>> $rows @return list<ExternalTenantBinding> */
    private function bindings(array $rows): array
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

    private function bindingFields(): string
    {
        return 'b.id,b.tenant_id,b.provider,b.callback_key,b.identity_hash,b.identity_hint,'
            . 'b.config_json,b.status,t.status AS tenant_status';
    }
}
