<?php
declare(strict_types=1);

namespace app\adminapi\logic\setting;

use app\common\logic\BaseLogic;
use app\common\service\ConfigService;
use app\common\service\FileService;

/**
 * 客服设置 Logic（纯配置）
 *
 * type = customer_service
 * 字段：qr_code（客服二维码，存相对 uri）、wechat、phone、service_time
 */
class CustomerServiceLogic extends BaseLogic
{
    protected const CONFIG_TYPE = 'customer_service';

    /** @var array<string,string> 字段白名单 => 默认值 */
    protected const FIELDS = [
        'qr_code'      => '',
        'wechat'       => '',
        'phone'        => '',
        'service_time' => '',
    ];

    /** 读取配置：qr_code 转为可访问 URL 回显 */
    public static function getConfig(): array
    {
        $stored = ConfigService::get(self::CONFIG_TYPE);
        $result = [];
        foreach (self::FIELDS as $field => $default) {
            $result[$field] = $stored[$field] ?? $default;
        }
        $result['qr_code'] = FileService::getFileUrl($result['qr_code']);
        return $result;
    }

    /** 保存配置：qr_code 存相对 uri */
    public static function setConfig(array $params): bool
    {
        $data = [];
        foreach (self::FIELDS as $field => $default) {
            if (array_key_exists($field, $params)) {
                $data[$field] = (string) $params[$field];
            }
        }
        if (isset($data['qr_code'])) {
            $data['qr_code'] = FileService::setFileUrl($data['qr_code']);
        }
        ConfigService::setMany(self::CONFIG_TYPE, $data);
        return true;
    }
}
