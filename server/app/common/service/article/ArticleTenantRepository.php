<?php
declare(strict_types=1);

namespace app\common\service\article;

use app\Modules\Official\Article\ModuleProvider;
use app\common\model\article\Article;
use app\common\model\article\ArticleCate;
use app\common\model\article\ArticleCollect;
use app\common\service\member\AuthenticatedMemberContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;
use think\facade\Db;

final class ArticleTenantRepository
{
    public static function articles(AuthenticatedMemberContext|TenantContext|TenantSystemContext $context)
    {
        self::assertAvailable($context);
        return Article::where('tenant_id', ArticleTenantContext::tenantId($context));
    }

    public static function categories(TenantContext|TenantSystemContext $context)
    {
        self::assertAvailable($context);
        return ArticleCate::where('tenant_id', ArticleTenantContext::tenantId($context));
    }

    public static function collections(AuthenticatedMemberContext|TenantContext|TenantSystemContext $context)
    {
        self::assertAvailable($context);
        return ArticleCollect::where('tenant_id', ArticleTenantContext::tenantId($context));
    }

    public static function createArticle(TenantContext $context, array $data): Article
    {
        self::assertAvailable($context);
        unset($data['tenant_id']);
        return Article::create(['tenant_id' => ArticleTenantContext::tenantId($context)] + $data);
    }

    public static function createCategory(TenantContext $context, array $data): ArticleCate
    {
        self::assertAvailable($context);
        unset($data['tenant_id']);
        return ArticleCate::create(['tenant_id' => ArticleTenantContext::tenantId($context)] + $data);
    }

    public static function createCollection(
        AuthenticatedMemberContext|TenantContext $context,
        array $data
    ): ArticleCollect
    {
        self::assertAvailable($context);
        unset($data['tenant_id']);
        return ArticleCollect::create(['tenant_id' => ArticleTenantContext::tenantId($context)] + $data);
    }

    private static function assertAvailable(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context
    ): void {
        $pdo = Db::connect()->connect();
        if (!$pdo instanceof \PDO) {
            throw new \RuntimeException('ARTICLE_MODULE_DATABASE_UNAVAILABLE');
        }
        (new ModuleProvider())->access($pdo)->assertTenant(ArticleTenantContext::tenantId($context));
    }
}
