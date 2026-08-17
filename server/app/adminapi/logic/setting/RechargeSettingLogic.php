<?php
declare(strict_types=1);

namespace app\adminapi\logic\setting;

use app\common\enum\UserTerminalEnum;
use app\common\logic\BaseLogic;
use app\common\model\finance\PaymentScene;
use app\common\service\finance\RechargeTenantSettingService;
use PeanutAdmin\Kernel\Auth\TenantContext;

class RechargeSettingLogic extends BaseLogic
{
    public static function getConfig(TenantContext $context): array
    {
        $config = RechargeTenantSettingService::config($context);
        $config['status'] = (int)$config['status'];
        $config['min_amount'] = self::amount($config['min_amount']);
        $config['max_amount'] = self::amount($config['max_amount']);
        return $config;
    }

    /**
     * 返回指定终端当前可用于充值的渠道，默认渠道排在首位。
     *
     * @return array<int, array{pay_way:int,is_default:int}>
     */
    public static function availablePayWays(TenantContext $context, int $terminal): array
    {
        if (!UserTerminalEnum::isValid($terminal)
            || (int)RechargeTenantSettingService::config($context)['status'] !== 1) {
            return [];
        }

        return array_values(array_filter(
            RechargeTenantSettingService::enabledScenes($context, $terminal),
            static fn(array $scene): bool => RechargeTenantSettingService::channelConfigured(
                $context,
                (int)$scene['pay_way']
            )
        ));
    }

    public static function save(TenantContext $context, array $params): bool
    {
        try {
            foreach ($params['scenes'] as $scene) {
                if ((int)$scene['status'] === 1
                    && !RechargeTenantSettingService::channelConfigured($context, (int)$scene['pay_way'])) {
                    throw new \RuntimeException(PaymentScene::getPayWayDesc((int)$scene['pay_way']) . '未启用，不能用于充值场景');
                }
            }
            RechargeTenantSettingService::replace($context, [
                'status' => (int)$params['status'],
                'min_amount' => self::amount($params['min_amount']),
                'max_amount' => self::amount($params['max_amount']),
                'scenes' => array_map(static fn(array $scene): array => [
                    'terminal' => (int)$scene['terminal'],
                    'pay_way' => (int)$scene['pay_way'],
                    'status' => (int)$scene['status'],
                    'is_default' => (int)$scene['is_default'],
                ], $params['scenes']),
            ]);
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    private static function amount(mixed $value): string
    {
        return number_format((float)$value, 2, '.', '');
    }
}
