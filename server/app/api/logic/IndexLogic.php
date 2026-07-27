<?php
declare(strict_types=1);

namespace app\api\logic;

use app\common\logic\BaseLogic;
use app\common\model\article\Article;
use app\common\model\article\ArticleCate;
use app\common\service\ConfigService;
use app\common\service\FileService;

class IndexLogic extends BaseLogic
{
    /** 全局配置（uniapp / H5 用） */
    public static function getConfigData(): array
    {
        $domain    = request()->domain();
        $shopName  = (string) ConfigService::get('website', 'shop_name', '');
        $shopLogo  = FileService::getFileUrl((string) ConfigService::get('website', 'shop_logo', ''));

        return [
            'domain'   => $domain,
            'website'  => [
                'shop_name' => $shopName,
                'shop_logo' => $shopLogo,
            ],
            'login'    => [
                'login_way' => (int) ConfigService::get('login', 'login_way', 1),
            ],
            'version'  => '1.0.0',
        ];
    }

    /** 政策协议（type: privacy | service） */
    public static function getPolicyByType(string $type): array
    {
        return [
            'title'   => (string) ConfigService::get('agreement', $type . '_title', ''),
            'content' => (string) ConfigService::get('agreement', $type . '_content', ''),
        ];
    }

    /** 首页数据 */
    public static function getIndexData(): array
    {
        $field = ['id', 'cate_id', 'title', 'intro', 'image', 'author', 'click_num', 'create_time'];
        $articles = Article::field($field)
            ->where('is_show', 1)
            ->with(['cate'])
            ->order(['sort' => 'desc', 'id' => 'desc'])
            ->limit(20)
            ->select()
            ->toArray();

        foreach ($articles as &$row) {
            $row['cate_name'] = $row['cate']['name'] ?? '';
            unset($row['cate']);
        }
        unset($row);

        return ['article' => $articles];
    }
}
