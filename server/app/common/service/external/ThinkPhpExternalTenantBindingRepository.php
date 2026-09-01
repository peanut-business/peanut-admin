<?php
declare(strict_types=1);

namespace app\common\service\external;

use app\Modules\Official\Oauth\Contracts\OAuthCallbackLocator;
use think\facade\Db;

final class ThinkPhpExternalTenantBindingRepository implements ExternalTenantBindingRepository, ExternalChannelBindingStore
{
    public function __construct(private readonly OAuthCallbackLocator $oauthCallbacks)
    {
    }

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
        return $this->oauthCallbacks->locateState($provider, $stateHash);
    }

    public function byOAuthTicket(string $ticketHash): array
    {
        return $this->oauthCallbacks->locateTicket($ticketHash);
    }

    public function tenantIsActive(int $tenantId): bool
    {
        return (string)Db::name('tenant')->where('id', $tenantId)->value('status') === 'active';
    }

    public function updateBinding(
        int $tenantId,
        string $provider,
        array $config,
        string $identity,
        bool $enabled,
    ): void {
        Db::transaction(function () use ($tenantId, $provider, $config, $identity, $enabled): void {
            $this->assertActiveTenant($tenantId, true);
            $binding = $this->lockedBinding($tenantId, $provider);
            $this->persistLocked($tenantId, $provider, $config, $identity, $enabled, $binding);
        });
    }

    public function mutateBinding(
        int $tenantId,
        string $provider,
        string $identity,
        callable $mutation,
        ?string $identityHint = null,
    ): void {
        Db::transaction(function () use ($tenantId, $provider, $identity, $mutation, $identityHint): void {
            $this->assertActiveTenant($tenantId, true);
            $binding = $this->lockedBinding($tenantId, $provider);
            $current = [];
            if (is_array($binding)) {
                $decoded = json_decode((string)($binding['config_json'] ?? ''), true);
                $current = is_array($decoded) ? $decoded : [];
            }
            $change = $mutation($current);
            $this->persistLocked(
                $tenantId,
                $provider,
                $change['config'],
                $identity,
                $change['enabled'],
                $binding,
                $identityHint,
            );
        });
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

    /** @return array<string, mixed>|null */
    private function lockedBinding(int $tenantId, string $provider): ?array
    {
        $binding = Db::name('external_channel_binding')
            ->where('tenant_id', $tenantId)
            ->where('provider', $provider)
            ->lock(true)
            ->find();
        return is_array($binding) ? $binding : null;
    }

    private function assertActiveTenant(int $tenantId, bool $lock = false): void
    {
        $query = Db::name('tenant')->where('id', $tenantId);
        if ($lock) {
            $query->lock(true);
        }
        if ((string)$query->value('status') !== 'active') {
            throw new ExternalTenantResolutionException();
        }
    }

    /** @param array<string, mixed>|null $binding */
    private function persistLocked(
        int $tenantId,
        string $provider,
        array $config,
        string $identity,
        bool $enabled,
        ?array $binding,
        ?string $identityHint = null,
    ): void {
        $encoded = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
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
                'identity_hint' => $identityHint ?? ($identity !== '' ? substr($identity, -8) : ''),
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
            $update['identity_hint'] = $identityHint ?? substr($identity, -8);
        }
        Db::name('external_channel_binding')
            ->where('id', (int)$binding['id'])
            ->where('tenant_id', $tenantId)
            ->update($update);
    }

    private static function callbackKeyForUpdate(string $provider, string $current, bool $enabled): string
    {
        $freshPlaceholder = hash('sha256', 'fresh-default:' . $provider);
        if ($current === '' || ($enabled && hash_equals($freshPlaceholder, $current))) {
            return bin2hex(random_bytes(32));
        }
        return $current;
    }
}
