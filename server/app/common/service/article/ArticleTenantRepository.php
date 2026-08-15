<?php
declare(strict_types=1);

namespace app\common\service\article;

use app\common\model\article\Article;
use app\common\model\article\ArticleCate;
use app\common\model\article\ArticleCollect;
use app\common\service\member\AuthenticatedMemberContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

final class ArticleTenantRepository
{
    public static function articles(AuthenticatedMemberContext|TenantContext|TenantSystemContext $context)
    {
        return Article::where('tenant_id', ArticleTenantContext::tenantId($context));
    }

    public static function categories(TenantContext|TenantSystemContext $context)
    {
        return ArticleCate::where('tenant_id', ArticleTenantContext::tenantId($context));
    }

    public static function collections(AuthenticatedMemberContext|TenantContext|TenantSystemContext $context)
    {
        return ArticleCollect::where('tenant_id', ArticleTenantContext::tenantId($context));
    }

    public static function createArticle(TenantContext $context, array $data): Article
    {
        unset($data['tenant_id']);
        return Article::create(['tenant_id' => ArticleTenantContext::tenantId($context)] + $data);
    }

    public static function createCategory(TenantContext $context, array $data): ArticleCate
    {
        unset($data['tenant_id']);
        return ArticleCate::create(['tenant_id' => ArticleTenantContext::tenantId($context)] + $data);
    }

    public static function createCollection(
        AuthenticatedMemberContext|TenantContext $context,
        array $data
    ): ArticleCollect
    {
        unset($data['tenant_id']);
        return ArticleCollect::create(['tenant_id' => ArticleTenantContext::tenantId($context)] + $data);
    }
}
