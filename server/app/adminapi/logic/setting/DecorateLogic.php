<?php
declare(strict_types=1);

namespace app\adminapi\logic\setting;

use app\common\logic\BaseLogic;
use app\common\service\ConfigService;
use app\common\service\FileService;

/**
 * 页面装修 Logic
 *
 * type = decorate
 * 存储首页各区块的配置（轮播图、公告、快捷入口等），值为 JSON 字符串。
 */
class DecorateLogic extends BaseLogic
{
    protected const CONFIG_TYPE = 'decorate';

    /** banner 项结构：{image, link, sort} */
    protected const FIELDS = [
        'banners'     => '[]',  // JSON 数组：轮播图列表
        'notice'      => '',    // 首页公告文本
        'notice_show' => 0,     // 公告显示开关
        'hot_show'    => 1,     // 热门搜索区块显示
        'news_show'   => 1,     // 资讯区块显示
    ];

    public static function getConfig(): array
    {
        $stored = ConfigService::get(self::CONFIG_TYPE);
        $result = [];
        foreach (self::FIELDS as $field => $default) {
            $value = $stored[$field] ?? $default;
            $result[$field] = $value;
        }

        // banners：JSON → array，并将每项 image 转为可访问 URL
        $banners = json_decode((string) $result['banners'], true) ?: [];
        foreach ($banners as &$banner) {
            if (!empty($banner['image'])) {
                $banner['image'] = FileService::getFileUrl($banner['image']);
            }
        }
        unset($banner);
        $result['banners'] = $banners;

        // 开关字段转整型
        foreach (['notice_show', 'hot_show', 'news_show'] as $sw) {
            $result[$sw] = (int) $result[$sw];
        }

        return $result;
    }

    public static function setConfig(array $params): bool
    {
        $data = [];

        // banners：将 image 转为相对 uri 后 json_encode
        if (array_key_exists('banners', $params)) {
            $banners = is_array($params['banners']) ? $params['banners'] : [];
            foreach ($banners as &$banner) {
                if (!empty($banner['image'])) {
                    $banner['image'] = FileService::setFileUrl($banner['image']);
                }
            }
            unset($banner);
            $data['banners'] = json_encode($banners, JSON_UNESCAPED_UNICODE);
        }

        foreach (['notice', 'notice_show', 'hot_show', 'news_show'] as $field) {
            if (array_key_exists($field, $params)) {
                $data[$field] = (string) $params[$field];
            }
        }

        ConfigService::setMany(self::CONFIG_TYPE, $data);
        return true;
    }
}
