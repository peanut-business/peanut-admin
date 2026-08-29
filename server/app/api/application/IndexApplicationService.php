<?php
declare(strict_types=1);

namespace app\api\application;

use app\common\application\ApplicationService;
use app\common\service\article\ArticleTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;
use app\common\service\DemoAccountPolicy;
use app\common\service\FileService;
use app\common\service\ProductAssetReferenceService;
use app\common\service\RichTextResourceService;
use app\common\service\config\TenantApplicationSettingService;
use app\common\service\config\TenantSettingWebsiteStore;
use app\common\service\config\WebsiteConfigService;
use app\common\enum\decoration\DecorationEnum;
use app\common\service\decoration\DecorationReadService;
use app\common\service\decoration\DecorationTenantContext;
use app\common\service\tenant\TenantEntryBindingResolver;
use app\common\service\tenant\TenantIdentityQuery;

class IndexApplicationService extends ApplicationService
{
    /** 全局配置（uniapp / H5 用） */
    public function getConfigData(TenantContext|TenantSystemContext $context): array
    {
        $domain    = request()->domain();
        $website = self::websiteService($context)->get();
        $login = TenantApplicationSettingService::login($context);
        $statistics = TenantApplicationSettingService::statistics($context);
        $webPageSetting = TenantApplicationSettingService::webPage($context);
        $webPage   = [
            'status'      => (int)$webPageSetting['status'],
            'page_status' => (int)$webPageSetting['page_status'],
            'page_url'    => (string)$webPageSetting['page_url'],
            'url'         => rtrim($domain, '/') . '/mobile',
        ];

        return [
            'domain'   => $domain,
            'website'  => $website,
            'tenantName' => self::entryTenantName(),
            'demo'     => self::demoLogin(),
            'login'    => [
                'login_way' => $login['login_way'],
                'coerce_mobile' => (int)$login['coerce_mobile'],
                'login_agreement' => (int)$login['login_agreement'],
                'third_auth' => (int)$login['third_auth'],
                'wechat_auth' => (int)$login['wechat_auth'],
            ],
            'copyright' => self::copyright($context),
            'site_statistics' => [
                'clarity_code' => (string)$statistics['clarity_code'],
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
            'version'  => (string) config('project.version', '2.0.1'),
        ];
    }

    /** @return array{enabled:bool,email:string,password:string} */
    private static function demoLogin(): array
    {
        if (!DemoAccountPolicy::enabled()) {
            return ['enabled' => false, 'email' => '', 'password' => ''];
        }
        try {
            $host = TenantEntryBindingResolver::normalizeHost((string)request()->host());
            $tenantAHost = TenantEntryBindingResolver::normalizeHost(
                (string)(getenv('PEANUT_DEMO_TENANT_A_HOST') ?: '')
            );
            $tenantBHost = TenantEntryBindingResolver::normalizeHost(
                (string)(getenv('PEANUT_DEMO_TENANT_B_HOST') ?: '')
            );
            $sharedHosts = array_filter(array_map(
                static fn(string $value): string => TenantEntryBindingResolver::normalizeHost($value),
                explode(',', (string)(getenv('TENANT_ADMIN_HOSTS') ?: ''))
            ));
        } catch (\Throwable) {
            return ['enabled' => false, 'email' => '', 'password' => ''];
        }
        if (hash_equals($tenantBHost, $host)) {
            $emailKey = 'PEANUT_DEMO_TENANT_B_EMAIL';
        } elseif (hash_equals($tenantAHost, $host) || in_array($host, $sharedHosts, true)) {
            $emailKey = 'PEANUT_DEMO_TENANT_A_EMAIL';
        } else {
            return ['enabled' => false, 'email' => '', 'password' => ''];
        }
        return [
            'enabled' => true,
            'email' => trim((string)(getenv($emailKey) ?: '')),
            'password' => (string)(getenv('PEANUT_DEMO_SHARED_PASSWORD') ?: ''),
        ];
    }

    private static function entryTenantName(): string
    {
        try {
            $tenantId = TenantEntryBindingResolver::production()->boundTenantId(
                request(),
                TenantEntryBindingResolver::ADMIN_CLIENT
            );
            if ($tenantId === null) {
                return '';
            }
            return app(TenantIdentityQuery::class)->activeName($tenantId);
        } catch (\Throwable) {
            return '';
        }
    }

    private static function websiteService(TenantContext|TenantSystemContext $context): WebsiteConfigService
    {
        return new WebsiteConfigService(
            new TenantSettingWebsiteStore($context),
            static fn(string $value): string => FileService::getFileUrl($value),
            fn(string $value): string => FileService::setTenantFileUrl($context, $value),
        );
    }

    private static function copyright(TenantContext|TenantSystemContext $context): array
    {
        $document = TenantApplicationSettingService::copyright($context);
        return is_array($document['config'] ?? null) ? $document['config'] : [];
    }

    /** 政策协议（type: privacy | service） */
    public function getPolicyByType(
        TenantContext|TenantSystemContext $context,
        string $type,
    ): array
    {
        $setting = TenantApplicationSettingService::agreement($context);
        $prefix = $type === 'privacy' ? 'privacy' : 'service';
        return [
            'title'   => (string)$setting[$prefix . '_title'],
            'content' => RichTextResourceService::forRead(
                (string)$setting[$prefix . '_content']
            ),
        ];
    }

    /** 首页数据 */
    public function getIndexData(TenantContext|TenantSystemContext $context): array
    {
        $field = [
            'id', 'title', 'desc', 'abstract', 'image', 'author',
            'click_actual', 'click_virtual', 'create_time',
        ];
        $articles = ArticleTenantRepository::articles()->field($field)
            ->where('is_show', 1)
            ->order('id', 'desc')
            ->limit(20)
            ->select()
            ->toArray();

        foreach ($articles as &$row) {
            $row['click'] = (int) $row['click_actual'] + (int) $row['click_virtual'];
            $row['image'] = ProductAssetReferenceService::forRead((string)($row['image'] ?? ''));
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
