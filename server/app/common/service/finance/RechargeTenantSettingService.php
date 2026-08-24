<?php
declare(strict_types=1);

namespace app\common\service\finance;

use app\common\model\finance\PaymentScene;
use app\common\service\external\ExternalChannelBindingService;
use app\common\service\external\ExternalTenantResolver;
use app\common\service\member\AuthenticatedMemberContext;
use app\common\service\tenant\TenantSettingsRuntimeFactory;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

final class RechargeTenantSettingService
{
    public const NAMESPACE = 'finance.recharge';

    public static function config(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context
    ): array {
        return TenantSettingsRuntimeFactory::service()->get($context, self::NAMESPACE, self::defaults())->document;
    }

    public static function replace(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        array $config
    ): void {
        TenantSettingsRuntimeFactory::service()->replace($context, self::NAMESPACE, $config);
    }

    public static function enabledScenes(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        int $terminal
    ): array {
        $config = self::config($context);
        $scenes = array_filter(
            is_array($config['scenes'] ?? null) ? $config['scenes'] : [],
            static fn(array $scene): bool => (int)($scene['terminal'] ?? 0) === $terminal
                && (int)($scene['status'] ?? 0) === PaymentScene::STATUS_ENABLED
        );
        usort($scenes, static fn(array $left, array $right): int =>
            [(int)($right['is_default'] ?? 0), (int)($left['pay_way'] ?? 0)]
            <=> [(int)($left['is_default'] ?? 0), (int)($right['pay_way'] ?? 0)]
        );
        return array_values($scenes);
    }

    public static function scene(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        int $terminal,
        int $payWay
    ): ?array {
        foreach (self::enabledScenes($context, $terminal) as $scene) {
            if ((int)$scene['pay_way'] === $payWay) {
                return $scene;
            }
        }
        return null;
    }

    public static function defaultScene(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        int $terminal
    ): ?array {
        foreach (self::enabledScenes($context, $terminal) as $scene) {
            if ((int)($scene['is_default'] ?? 0) === 1) {
                return $scene;
            }
        }
        return null;
    }

    public static function channelConfigured(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        int $payWay
    ): bool {
        $provider = match ($payWay) {
            PaymentScene::PAY_WAY_WECHAT => ExternalTenantResolver::WECHAT_PAYMENT,
            PaymentScene::PAY_WAY_ALIPAY => ExternalTenantResolver::ALIPAY_PAYMENT,
            default => '',
        };
        if ($provider === '') {
            return false;
        }
        try {
            $config = ExternalChannelBindingService::config($context, $provider);
        } catch (\Throwable) {
            return false;
        }
        return $payWay === PaymentScene::PAY_WAY_WECHAT
            ? (int)($config['wx_pay_status'] ?? 0) === 1
            : (int)($config['ali_pay_status'] ?? 0) === 1;
    }

    private static function defaults(): array
    {
        $scenes = PaymentScene::field('terminal,pay_way,status,is_default')
            ->order('terminal', 'asc')
            ->order('pay_way', 'asc')
            ->select()
            ->toArray();
        return [
            'status' => 0,
            'min_amount' => '0.01',
            'max_amount' => '99999.00',
            'scenes' => array_map(static fn(array $scene): array => [
                'terminal' => (int)$scene['terminal'],
                'pay_way' => (int)$scene['pay_way'],
                'status' => (int)$scene['status'],
                'is_default' => (int)$scene['is_default'],
            ], $scenes),
        ];
    }
}
