<?php
declare(strict_types=1);

namespace app\adminapi\logic\setting;

use app\common\enum\UserTerminalEnum;
use app\common\logic\BaseLogic;
use app\common\model\finance\PaymentScene;
use app\common\service\ConfigService;
use think\facade\Db;

class RechargeSettingLogic extends BaseLogic
{
    private const CONFIG_TYPE = 'recharge';

    public static function getConfig(): array
    {
        $scenes = PaymentScene::field('terminal,pay_way,status,is_default')
            ->order('terminal', 'asc')
            ->order('pay_way', 'asc')
            ->select()
            ->toArray();

        return [
            'status' => (int)ConfigService::get(self::CONFIG_TYPE, 'status', 0),
            'min_amount' => self::amount(ConfigService::get(self::CONFIG_TYPE, 'min_amount', '0.01')),
            'max_amount' => self::amount(ConfigService::get(self::CONFIG_TYPE, 'max_amount', '99999.00')),
            'scenes' => array_map(static fn(array $scene): array => [
                'terminal' => (int)$scene['terminal'],
                'pay_way' => (int)$scene['pay_way'],
                'status' => (int)$scene['status'],
                'is_default' => (int)$scene['is_default'],
            ], $scenes),
        ];
    }

    /**
     * 返回指定终端当前可用于充值的渠道，默认渠道排在首位。
     *
     * @return array<int, array{pay_way:int,is_default:int}>
     */
    public static function availablePayWays(int $terminal): array
    {
        if (!UserTerminalEnum::isValid($terminal)
            || (int)ConfigService::get(self::CONFIG_TYPE, 'status', 0) !== 1) {
            return [];
        }

        return array_values(array_filter(
            PaymentScene::enabledPayWays($terminal),
            static function (array $scene): bool {
                return (int)$scene['pay_way'] === PaymentScene::PAY_WAY_WECHAT
                    ? (int)ConfigService::get('pay', 'wx_pay_status', 0) === 1
                    : (int)ConfigService::get('pay', 'ali_pay_status', 0) === 1;
            }
        ));
    }

    public static function save(array $params): bool
    {
        try {
            Db::transaction(function () use ($params): void {
                ConfigService::setMany(self::CONFIG_TYPE, [
                    'status' => (int)$params['status'],
                    'min_amount' => self::amount($params['min_amount']),
                    'max_amount' => self::amount($params['max_amount']),
                ]);

                foreach ($params['scenes'] as $scene) {
                    $identity = [
                        'terminal' => (int)$scene['terminal'],
                        'pay_way' => (int)$scene['pay_way'],
                    ];
                    $row = PaymentScene::where($identity)->lock(true)->findOrEmpty();
                    $values = [
                        'status' => (int)$scene['status'],
                        'is_default' => (int)$scene['is_default'],
                    ];
                    if ($row->isEmpty()) {
                        PaymentScene::create($identity + $values);
                    } else {
                        $row->save($values);
                    }
                }
            });
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
