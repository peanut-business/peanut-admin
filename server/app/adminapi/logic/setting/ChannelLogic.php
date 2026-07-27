<?php
declare(strict_types=1);

namespace app\adminapi\logic\setting;

use app\common\logic\BaseLogic;
use app\common\service\ConfigService;

/**
 * 渠道配置 Logic（第三方登录渠道）
 *
 * type = channel
 * 字段：微信开放平台（wechat_open_*）、微信小程序（wechat_mini_*）、QQ（qq_*）
 */
class ChannelLogic extends BaseLogic
{
    protected const CONFIG_TYPE = 'channel';

    /** @var array<string,mixed> 字段白名单 => 默认值 */
    protected const FIELDS = [
        // 微信开放平台（PC/H5 扫码登录）
        'wechat_open_status'    => 0,
        'wechat_open_appid'     => '',
        'wechat_open_secret'    => '',
        // 微信小程序
        'wechat_mini_status'    => 0,
        'wechat_mini_appid'     => '',
        'wechat_mini_secret'    => '',
        // 微信公众号
        'wechat_oa_status'      => 0,
        'wechat_oa_appid'       => '',
        'wechat_oa_secret'      => '',
        // QQ
        'qq_status'             => 0,
        'qq_appid'              => '',
        'qq_secret'             => '',
    ];

    public static function getConfig(): array
    {
        $stored = ConfigService::get(self::CONFIG_TYPE);
        $result = [];
        $statusFields = ['wechat_open_status', 'wechat_mini_status', 'wechat_oa_status', 'qq_status'];
        foreach (self::FIELDS as $field => $default) {
            $value = $stored[$field] ?? $default;
            if (in_array($field, $statusFields, true)) {
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
