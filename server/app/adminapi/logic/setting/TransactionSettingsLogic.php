<?php
declare(strict_types=1);

namespace app\adminapi\logic\setting;

use app\common\logic\BaseLogic;
use app\common\service\ConfigService;

/**
 * 交易设置 Logic
 *
 * 4 个配置项（均存于 pa_config type=transaction）：
 *   cancel_unpaid_orders       0关/1开：自动取消未付款订单
 *   cancel_unpaid_orders_times 自动取消时间（分钟）
 *   verification_orders        0关/1开：自动核销订单
 *   verification_orders_times  自动核销时间（小时）
 */
class TransactionSettingsLogic extends BaseLogic
{
    protected const CONFIG_TYPE = 'transaction';

    public static function getConfig(): array
    {
        return [
            'cancel_unpaid_orders'       => (int) ConfigService::get(self::CONFIG_TYPE, 'cancel_unpaid_orders', 1),
            'cancel_unpaid_orders_times' => (int) ConfigService::get(self::CONFIG_TYPE, 'cancel_unpaid_orders_times', 30),
            'verification_orders'        => (int) ConfigService::get(self::CONFIG_TYPE, 'verification_orders', 1),
            'verification_orders_times'  => (int) ConfigService::get(self::CONFIG_TYPE, 'verification_orders_times', 24),
        ];
    }

    public static function setConfig(array $params): void
    {
        ConfigService::set(self::CONFIG_TYPE, 'cancel_unpaid_orders',       (int) $params['cancel_unpaid_orders']);
        ConfigService::set(self::CONFIG_TYPE, 'verification_orders',        (int) $params['verification_orders']);

        if (isset($params['cancel_unpaid_orders_times'])) {
            ConfigService::set(self::CONFIG_TYPE, 'cancel_unpaid_orders_times', (int) $params['cancel_unpaid_orders_times']);
        }

        if (isset($params['verification_orders_times'])) {
            ConfigService::set(self::CONFIG_TYPE, 'verification_orders_times', (int) $params['verification_orders_times']);
        }
    }
}
