<?php
declare(strict_types=1);

namespace app\api\logic;

use app\common\logic\BaseLogic;
use app\common\model\article\Article;
use app\common\model\article\ArticleCate;
use app\common\service\ConfigService;
use app\common\service\FileService;
use app\common\service\RichTextResourceService;
use app\common\enum\decoration\DecorationEnum;
use app\common\service\decoration\DecorationReadService;

class IndexLogic extends BaseLogic
{
    /** 全局配置（uniapp / H5 用） */
    public static function getConfigData(): array
    {
        $domain    = request()->domain();
        $website = [
            'shop_name' => (string)ConfigService::get('website', 'shop_name', ''),
            'shop_logo' => FileService::getFileUrl((string)ConfigService::get('website', 'shop_logo', '')),
            'pc_logo' => FileService::getFileUrl((string)ConfigService::get('website', 'pc_logo', '')),
            'pc_title' => (string)ConfigService::get('website', 'pc_title', ''),
            'pc_ico' => FileService::getFileUrl((string)ConfigService::get('website', 'pc_ico', '')),
            'pc_desc' => (string)ConfigService::get('website', 'pc_desc', ''),
            'pc_keywords' => (string)ConfigService::get('website', 'pc_keywords', ''),
            'h5_favicon' => FileService::getFileUrl((string)ConfigService::get('website', 'h5_favicon', '')),
        ];
        $loginWayRaw = ConfigService::get('login', 'login_way', '[1,2]');
        $loginWay = is_array($loginWayRaw) ? $loginWayRaw : json_decode((string)$loginWayRaw, true);
        if (!is_array($loginWay)) {
            $loginWay = [1, 2];
        }
        $webPage   = [
            'status'      => (int) ConfigService::get('web_page', 'status', 1),
            'page_status' => (int) ConfigService::get('web_page', 'page_status', 0),
            'page_url'    => (string) ConfigService::get('web_page', 'page_url', ''),
            'url'         => rtrim($domain, '/') . '/mobile',
        ];

        return [
            'domain'   => $domain,
            'website'  => $website,
            'login'    => [
                'login_way' => array_values(array_map('intval', $loginWay)),
                'coerce_mobile' => (int)ConfigService::get('login', 'coerce_mobile', 0),
                'login_agreement' => (int)ConfigService::get('login', 'login_agreement', 0),
                'third_auth' => (int)ConfigService::get('login', 'third_auth', 0),
                'wechat_auth' => (int)ConfigService::get('login', 'wechat_auth', 0),
            ],
            'copyright' => self::copyright(),
            'site_statistics' => [
                'clarity_code' => (string)ConfigService::get('site_statistics', 'clarity_code', ''),
            ],
            'web_page' => $webPage,
            'tabbar'   => DecorationReadService::tabbar(true),
            'theme'    => DecorationReadService::pageByType(DecorationEnum::SYSTEM_THEME),
            'version'  => '1.0.0',
        ];
    }

    private static function copyright(): array
    {
        $raw = ConfigService::get('copyright', 'config', '[]');
        if (is_array($raw)) {
            return $raw;
        }
        $decoded = json_decode((string)$raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** 政策协议（type: privacy | service） */
    public static function getPolicyByType(string $type): array
    {
        return [
            'title'   => (string) ConfigService::get('agreement', $type . '_title', ''),
            'content' => RichTextResourceService::forRead(
                (string)ConfigService::get('agreement', $type . '_content', '')
            ),
        ];
    }

    /** 首页数据 */
    public static function getIndexData(): array
    {
        $field = [
            'id', 'title', 'desc', 'abstract', 'image', 'author',
            'click_actual', 'click_virtual', 'create_time',
        ];
        $articles = Article::field($field)
            ->where('is_show', 1)
            ->order('id', 'desc')
            ->limit(20)
            ->select()
            ->toArray();

        foreach ($articles as &$row) {
            $row['click'] = (int) $row['click_actual'] + (int) $row['click_virtual'];
            unset($row['click_actual'], $row['click_virtual']);
        }
        unset($row);

        return [
            'article' => $articles,
            'decorate' => DecorationReadService::pageByType(DecorationEnum::MOBILE_HOME),
        ];
    }
}
