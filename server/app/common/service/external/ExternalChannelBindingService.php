<?php
declare(strict_types=1);

namespace app\common\service\external;

use PDO;
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
        Db::transaction(function () use ($tenantId, $provider, $config, $identity, $enabled): void {
            self::assertActiveTenant($tenantId, true);
            $binding = Db::name('external_channel_binding')
                ->where('tenant_id', $tenantId)
                ->where('provider', $provider)
                ->lock(true)
                ->find();
            self::assertValidInput($provider, $identity, $enabled);
            self::persistLocked($tenantId, $provider, $config, $identity, $enabled, $binding);
        });
    }

    /**
     * Persist a configuration-transfer binding without accepting a callback key.
     *
     * The callback key is an installation-local routing credential. Existing
     * rows retain it, while a newly-created row always receives a fresh
     * server-generated key. The transfer package only carries the public
     * identity hash, so this method deliberately does not try to reconstruct a
     * provider identity from the imported configuration.
     *
     * @param array<string, mixed> $config
     */
    public static function importConfiguration(
        PDO $pdo,
        TenantContext $context,
        string $provider,
        array $config,
        ?string $identityHash,
        string $identityHint,
        bool $enabled,
        ?int $expectedRevision = null,
    ): void {
        $tenantId = ExternalTenantContext::tenantId($context);
        $provider = trim($provider);
        $identityHint = trim($identityHint);
        if (preg_match('/^[a-z][a-z0-9.-]{0,63}$/D', $provider) !== 1
            || strlen($identityHint) > 32
            || ($identityHash !== null && preg_match('/^[a-f0-9]{64}$/D', $identityHash) !== 1)
            || ($enabled && $identityHash === null)) {
            throw new \RuntimeException('TRANSFER_EXTERNAL_BINDING_INVALID');
        }

        self::assertActiveTenantOnPdo($pdo, $tenantId, true);
        $statement = $pdo->prepare(<<<'SQL'
SELECT id, identity_hash, identity_hint, config_json, status, update_time
FROM pa_external_channel_binding
WHERE tenant_id = :tenant_id AND provider = :provider
LIMIT 1
FOR UPDATE
SQL);
        $statement->execute([
            'tenant_id' => $tenantId,
            'provider' => $provider,
        ]);
        $binding = $statement->fetch(PDO::FETCH_ASSOC);
        $binding = is_array($binding) ? $binding : null;
        $currentRevision = $binding === null ? null : self::configurationRevision($binding);
        if (($expectedRevision === null && $binding !== null)
            || ($expectedRevision !== null && $currentRevision !== $expectedRevision)) {
            throw new \RuntimeException('TRANSFER_CONFLICT');
        }
        self::persistImportedLocked(
            $pdo,
            $tenantId,
            $provider,
            $config,
            $identityHash,
            $identityHint,
            $enabled,
            $binding,
        );
    }

    /**
     * Produce a positive, deterministic optimistic-concurrency token without
     * exposing binding configuration or requiring a legacy table migration.
     *
     * @param array<string, mixed> $binding
     */
    public static function configurationRevision(array $binding): int
    {
        $state = [];
        foreach (['identity_hash', 'identity_hint', 'config_json', 'status', 'update_time'] as $key) {
            $state[$key] = $binding[$key] ?? null;
        }
        try {
            $encoded = json_encode(
                $state,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (\JsonException) {
            throw new \RuntimeException('TRANSFER_EXTERNAL_BINDING_INVALID');
        }
        $revision = hexdec(substr(hash('sha256', $encoded), 0, 15));
        if (!is_int($revision) || $revision < 1) {
            throw new \RuntimeException('TRANSFER_EXTERNAL_BINDING_INVALID');
        }
        return $revision;
    }

    /**
     * Atomically read, mutate and persist a Tenant binding under the service-owned row lock.
     *
     * @param callable(array<string, mixed>): array<string, mixed> $mutator
     * @param callable(array<string, mixed>): bool|null $enabledResolver
     */
    public static function mutate(
        TenantContext $context,
        string $provider,
        string $identity,
        callable $mutator,
        ?callable $enabledResolver = null,
        ?string $identityHint = null,
    ): void {
        $tenantId = ExternalTenantContext::tenantId($context);
        $identity = strtolower(trim($identity));
        Db::transaction(function () use ($tenantId, $provider, $identity, $mutator, $enabledResolver, $identityHint): void {
            self::assertActiveTenant($tenantId, true);
            $binding = Db::name('external_channel_binding')
                ->where('tenant_id', $tenantId)
                ->where('provider', $provider)
                ->lock(true)
                ->find();
            $current = [];
            if (is_array($binding)) {
                $decoded = json_decode((string)($binding['config_json'] ?? ''), true);
                $current = is_array($decoded) ? $decoded : [];
            }
            $config = $mutator($current);
            if (!is_array($config)) {
                throw new \RuntimeException('外部渠道配置变更无效');
            }
            $enabled = $enabledResolver === null
                ? self::enabled($provider, $config)
                : (bool)$enabledResolver($config);
            self::assertValidInput($provider, $identity, $enabled);
            self::persistLocked($tenantId, $provider, $config, $identity, $enabled, $binding, $identityHint);
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

    private static function assertValidInput(string $provider, string $identity, bool $enabled): void
    {
        if (trim($provider) === '' || strlen($provider) > 64
            || ($enabled && $identity === '') || strlen($identity) > 191) {
            throw new \RuntimeException('外部渠道身份不能为空');
        }
    }

    /** @param array<string, mixed>|null $binding */
    private static function persistLocked(
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

    /** @param array<string, mixed>|null $binding @param array<string, mixed> $config */
    private static function persistImportedLocked(
        PDO $pdo,
        int $tenantId,
        string $provider,
        array $config,
        ?string $identityHash,
        string $identityHint,
        bool $enabled,
        ?array $binding,
    ): void {
        $encoded = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $now = time();
        if ($binding === null) {
            $statement = $pdo->prepare(<<<'SQL'
INSERT INTO pa_external_channel_binding
    (tenant_id, provider, callback_key, identity_hash, identity_hint, config_json, status, create_time, update_time)
VALUES
    (:tenant_id, :provider, :callback_key, :identity_hash, :identity_hint, :config_json, :status, :create_time, :update_time)
SQL);
            $statement->execute([
                'tenant_id' => $tenantId,
                'provider' => $provider,
                'callback_key' => bin2hex(random_bytes(32)),
                'identity_hash' => $identityHash
                    ?? hash('sha256', 'unconfigured:' . $provider . ':' . $tenantId),
                'identity_hint' => $identityHint,
                'config_json' => $encoded,
                'status' => $enabled ? 1 : 0,
                'create_time' => $now,
                'update_time' => $now,
            ]);
            return;
        }

        $columns = 'config_json = :config_json, status = :status, update_time = :update_time';
        $parameters = [
            'config_json' => $encoded,
            'status' => $enabled ? 1 : 0,
            'update_time' => $now,
            'id' => (int)$binding['id'],
            'tenant_id' => $tenantId,
            'provider' => $provider,
        ];
        if ($identityHash !== null) {
            $columns .= ', identity_hash = :identity_hash, identity_hint = :identity_hint';
            $parameters['identity_hash'] = $identityHash;
            $parameters['identity_hint'] = $identityHint;
        }
        $statement = $pdo->prepare(<<<SQL
UPDATE pa_external_channel_binding
SET {$columns}
WHERE id = :id AND tenant_id = :tenant_id AND provider = :provider
SQL);
        $statement->execute($parameters);
    }

    private static function assertActiveTenantOnPdo(PDO $pdo, int $tenantId, bool $lock = false): void
    {
        $statement = $pdo->prepare(
            'SELECT status FROM pa_tenant WHERE id = :tenant_id' . ($lock ? ' FOR UPDATE' : ''),
        );
        $statement->execute(['tenant_id' => $tenantId]);
        if ((string)$statement->fetchColumn() !== 'active') {
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
