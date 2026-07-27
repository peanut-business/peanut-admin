<?php
declare(strict_types=1);

namespace app\adminapi\logic\setting;

use app\common\logic\BaseLogic;
use app\common\service\ConfigService;

/**
 * 支付配置 Logic
 *
 * type = pay
 * 字段：微信支付（wx_*）+ 支付宝（ali_*）
 */
class PayConfigLogic extends BaseLogic
{
    protected const CONFIG_TYPE = 'pay';

    /** @var array<string,mixed> 字段白名单 => 默认值 */
    protected const FIELDS = [
        // 微信支付
        'wx_pay_status'        => 0,   // 0关 1开
        'wx_pay_appid'         => '',
        'wx_pay_mch_id'        => '',
        'wx_pay_secret'        => '',
        'wx_pay_cert_path'     => '',
        'wx_pay_cert_key_path' => '',
        // 支付宝
        'ali_pay_status'       => 0,   // 0关 1开
        'ali_pay_app_id'       => '',
        'ali_pay_private_key'  => '',
        'ali_pay_public_key'   => '',
    ];

    public static function getConfig(): array
    {
        $stored = ConfigService::get(self::CONFIG_TYPE);
        $result = [];
        foreach (self::FIELDS as $field => $default) {
            $value = $stored[$field] ?? $default;
            // 开关字段转整型
            if (in_array($field, ['wx_pay_status', 'ali_pay_status'], true)) {
                $value = (int) $value;
            }
            $result[$field] = $value;
        }
        return $result;
    }

    public static function setConfig(array $params): bool
    {
        $data = [];
        foreach (self::FIELDS as $field => $default) {
            if (array_key_exists($field, $params)) {
                $data[$field] = (string) $params[$field];
            }
        }
        ConfigService::setMany(self::CONFIG_TYPE, $data);
        return true;
    }
}
