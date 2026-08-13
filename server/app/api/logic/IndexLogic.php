<?php
declare(strict_types=1);

namespace app\api\logic;

use app\common\logic\BaseLogic;
use app\common\service\article\ArticleTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;
use app\common\service\ConfigService;
use app\common\service\FileService;
use app\common\service\RichTextResourceService;
use app\common\service\config\PaConfigWebsiteStore;
use app\common\service\config\WebsiteConfigService;
use app\common\enum\decoration\DecorationEnum;
use app\common\service\decoration\DecorationReadService;
use app\common\service\decoration\DecorationTenantContext;

class IndexLogic extends BaseLogic
{
    /** 全局配置（uniapp / H5 用） */
    public static function getConfigData(TenantContext|TenantSystemContext $context): array
    {
        $domain    = request()->domain();
        $website = self::websiteService()->get();
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
            'tabbar'   => DecorationReadService::tabbar(
                $context,
                true,
                DecorationTenantContext::CONFIG_OPERATION
            ),
            'theme'    => DecorationReadService::pageByType(
                $context,
                DecorationEnum::SYSTEM_THEME,
                DecorationTenantContext::CONFIG_OPERATION
            ),
            'version'  => (string) config('project.version', '1.1.0'),
        ];
    }

    private static function websiteService(): WebsiteConfigService
    {
        return new WebsiteConfigService(
            new PaConfigWebsiteStore(),
            static fn(string $value): string => FileService::getFileUrl($value),
            static fn(string $value): string => FileService::setFileUrl($value),
        );
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
    public static function getIndexData(TenantContext|TenantSystemContext $context): array
    {
        $field = [
            'id', 'title', 'desc', 'abstract', 'image', 'author',
            'click_actual', 'click_virtual', 'create_time',
        ];
        $articles = ArticleTenantRepository::articles($context)->field($field)
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
            'decorate' => DecorationReadService::pageByType(
                $context,
                DecorationEnum::MOBILE_HOME,
                DecorationTenantContext::ARTICLE_INDEX_OPERATION
            ),
        ];
    }
}
