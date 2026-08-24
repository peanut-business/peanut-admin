<?php
declare(strict_types=1);

namespace app\common\service\payment;

use app\common\model\finance\PaymentScene;
use app\common\service\external\ExternalTenantContext;
use app\common\service\external\ExternalTenantResolver;
use app\common\service\finance\FinanceTenantContext;
use think\facade\Db;

final class PaymentChannelGrantService
{
    public static function providerForPayWay(int $payWay): string
    {
        return match ($payWay) {
            PaymentScene::PAY_WAY_WECHAT => ExternalTenantResolver::WECHAT_PAYMENT,
            PaymentScene::PAY_WAY_ALIPAY => ExternalTenantResolver::ALIPAY_PAYMENT,
            default => throw new \RuntimeException('支付渠道不受支持'),
        };
    }

    public static function channelForPayWay(int $payWay): string
    {
        return match ($payWay) {
            PaymentScene::PAY_WAY_WECHAT => 'wechat',
            PaymentScene::PAY_WAY_ALIPAY => 'alipay',
            default => throw new \RuntimeException('支付渠道不受支持'),
        };
    }

    /** @return array<string,mixed> */
    public static function activeGrantForTenant(object $context, string $provider, bool $lock = false): array
    {
        $tenantId = FinanceTenantContext::tenantId($context);
        $query = Db::name('payment_tenant_channel_grant')->alias('g')
            ->field('g.id,g.tenant_id,g.provider,g.external_binding_id,g.merchant_account_ref,'
                . 'g.merchant_group_ref,b.callback_key,b.identity_hash,b.identity_hint,b.config_json')
            ->join('external_channel_binding b', 'b.id = g.external_binding_id')
            ->join('tenant t', 't.id = g.tenant_id')
            ->where('g.tenant_id', $tenantId)
            ->where('g.provider', $provider)
            ->where('g.status', 1)
            ->whereNull('g.revoked_at')
            ->where('b.provider', $provider)
            ->where('b.status', 1)
            ->where('t.status', 'active')
            ->limit(2);
        if ($lock) {
            $query->lock(true);
        }
        $rows = $query->select()->toArray();
        if (count($rows) !== 1) {
            throw new \RuntimeException('支付渠道未授权或已撤销');
        }
        $row = $rows[0];
        $config = json_decode((string)($row['config_json'] ?? ''), true);
        if (!is_array($config)) {
            throw new \RuntimeException('支付渠道配置无效');
        }
        $row['config'] = $config;
        unset($row['config_json']);
        return $row;
    }

    public static function channelConfigured(object $context, int $payWay): bool
    {
        try {
            $grant = self::activeGrantForTenant($context, self::providerForPayWay($payWay));
        } catch (\Throwable) {
            return false;
        }
        return $payWay === PaymentScene::PAY_WAY_WECHAT
            ? (int)($grant['config']['wx_pay_status'] ?? 0) === 1
            : (int)($grant['config']['ali_pay_status'] ?? 0) === 1;
    }

    public static function ensureSelfGrant(object $context, string $provider): void
    {
        $tenantId = ExternalTenantContext::tenantId($context);
        $binding = Db::name('external_channel_binding')
            ->where('tenant_id', $tenantId)
            ->where('provider', $provider)
            ->find();
        if (!is_array($binding)) {
            return;
        }
        self::grant(
            $tenantId,
            $provider,
            (int)$binding['id'],
            (string)($binding['identity_hash'] ?? ''),
            ''
        );
    }

    public static function grant(
        int $tenantId,
        string $provider,
        int $externalBindingId,
        string $merchantAccountRef = '',
        string $merchantGroupRef = ''
    ): int {
        $provider = trim($provider);
        if ($tenantId < 1 || $provider === '' || $externalBindingId < 1) {
            throw new \RuntimeException('支付渠道授权参数无效');
        }
        return (int)Db::transaction(function () use (
            $tenantId,
            $provider,
            $externalBindingId,
            $merchantAccountRef,
            $merchantGroupRef
        ): int {
            self::assertActiveTenant($tenantId, true);
            $binding = Db::name('external_channel_binding')
                ->where('id', $externalBindingId)
                ->where('provider', $provider)
                ->lock(true)
                ->find();
            if (!is_array($binding)) {
                throw new \RuntimeException('支付渠道账户不存在');
            }
            $now = time();
            Db::name('payment_tenant_channel_grant')
                ->where('tenant_id', $tenantId)
                ->where('provider', $provider)
                ->where('external_binding_id', '<>', $externalBindingId)
                ->where('status', 1)
                ->whereNull('revoked_at')
                ->update([
                    'status' => 0,
                    'revoked_at' => $now,
                    'update_time' => $now,
                ]);
            $existing = Db::name('payment_tenant_channel_grant')
                ->where('tenant_id', $tenantId)
                ->where('provider', $provider)
                ->where('external_binding_id', $externalBindingId)
                ->lock(true)
                ->find();
            $data = [
                'merchant_account_ref' => $merchantAccountRef !== ''
                    ? $merchantAccountRef
                    : (string)($binding['identity_hash'] ?? ''),
                'merchant_group_ref' => $merchantGroupRef,
                'status' => 1,
                'revoked_at' => null,
                'update_time' => $now,
            ];
            if (is_array($existing)) {
                Db::name('payment_tenant_channel_grant')
                    ->where('id', (int)$existing['id'])
                    ->update($data);
                return (int)$existing['id'];
            }
            return (int)Db::name('payment_tenant_channel_grant')->insertGetId([
                'tenant_id' => $tenantId,
                'provider' => $provider,
                'external_binding_id' => $externalBindingId,
                ...$data,
                'create_time' => $now,
            ]);
        });
    }

    public static function revoke(int $tenantId, string $provider, int $externalBindingId): void
    {
        if ($tenantId < 1 || trim($provider) === '' || $externalBindingId < 1) {
            throw new \RuntimeException('支付渠道授权参数无效');
        }
        Db::name('payment_tenant_channel_grant')
            ->where('tenant_id', $tenantId)
            ->where('provider', trim($provider))
            ->where('external_binding_id', $externalBindingId)
            ->update([
                'status' => 0,
                'revoked_at' => time(),
                'update_time' => time(),
            ]);
    }

    private static function assertActiveTenant(int $tenantId, bool $lock = false): void
    {
        $query = Db::name('tenant')->where('id', $tenantId);
        if ($lock) {
            $query->lock(true);
        }
        if ((string)$query->value('status') !== 'active') {
            throw new \RuntimeException('租户不可用');
        }
    }
}
