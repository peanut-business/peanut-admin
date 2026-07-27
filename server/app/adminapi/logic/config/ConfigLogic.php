<?php
declare(strict_types=1);

namespace app\adminapi\logic\config;

use app\common\logic\BaseLogic;
use app\common\service\ConfigService;

class ConfigLogic extends BaseLogic
{
    /** 网站设置字段白名单（type = website） */
    protected const WEBSITE_TYPE = 'website';
    protected const WEBSITE_FIELDS = [
        'name'      => '',   // 网站名称
        'logo'      => '',   // Logo 图片地址
        'favicon'   => '',   // 站点图标
        'copyright' => '',   // 版权信息
        'icp'       => '',   // ICP 备案号
    ];

    /** 读取网站设置，缺省字段补默认值 */
    public static function getWebsite(): array
    {
        $stored = ConfigService::get(self::WEBSITE_TYPE);
        $result = [];
        foreach (self::WEBSITE_FIELDS as $field => $default) {
            $result[$field] = $stored[$field] ?? $default;
        }
        return $result;
    }

    /** 保存网站设置，仅接受白名单字段 */
    public static function saveWebsite(array $params): bool
    {
        $data = [];
        foreach (self::WEBSITE_FIELDS as $field => $default) {
            if (array_key_exists($field, $params)) {
                $data[$field] = (string) $params[$field];
            }
        }
        ConfigService::setMany(self::WEBSITE_TYPE, $data);
        return true;
    }
}
