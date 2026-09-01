<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/route/registry_source.php';

function officialArticleExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$serverRoot = dirname(__DIR__, 2);
$repoRoot = dirname($serverRoot);
$moduleRoot = $serverRoot . '/app/Modules/Official/Article';
$manifest = json_decode(
    (string)file_get_contents($moduleRoot . '/module.json'),
    true,
    64,
    JSON_THROW_ON_ERROR
);

officialArticleExpect(($manifest['key'] ?? null) === 'official.article', 'official Article Module key changed');
officialArticleExpect(($manifest['tenant']['enableable'] ?? null) === true, 'official Article is not Tenant enableable');
officialArticleExpect(
    ($manifest['tenant']['disable_behavior'] ?? null) === 'reject_new_operations',
    'official Article disable behavior changed'
);
officialArticleExpect(
    ($manifest['database']['owned_tables'] ?? null) === ['pa_article_cate', 'pa_article', 'pa_article_collect'],
    'official Article table ownership changed'
);
officialArticleExpect(
    ($manifest['backend']['migrations'] ?? null) === 'Database/Migrations'
        && ($manifest['backend']['setting_definitions'] ?? null) === 'Resources/setting-definitions.json',
    'official Article manifest does not declare its migrations and setting definitions'
);
officialArticleExpect(
    json_decode((string)file_get_contents($moduleRoot . '/Resources/setting-definitions.json'), true, 8, JSON_THROW_ON_ERROR) === [],
    'official Article setting definition catalog must be explicitly empty'
);

$baseline = (string)file_get_contents($serverRoot . '/database/init.sql');
$ownershipMigration = (string)file_get_contents(
    $moduleRoot . '/Database/Migrations/20260825-adopt-permission-ownership.sql'
);
$namespaceMigration = (string)file_get_contents(
    $moduleRoot . '/Database/Migrations/20260826-namespace-permission-keys.sql'
);
officialArticleExpect(
    str_contains($baseline, "'article.articlecate/all'")
        && str_contains($ownershipMigration, "'article.articlecate/all'")
        && str_contains($namespaceMigration, "('article.articlecate/all',"),
    'official Article migration key does not exactly match the fresh baseline'
);
officialArticleExpect(
    !str_contains($ownershipMigration, "'article.articleCate/all'")
        && !str_contains($namespaceMigration, "('article.articleCate/all',"),
    'official Article migrations retain the case-mismatched category key'
);

$permissions = json_decode(
    (string)file_get_contents($moduleRoot . '/Resources/permissions.json'),
    true,
    64,
    JSON_THROW_ON_ERROR
);
$permissionKeys = array_column($permissions, 'key');
foreach ([
    'official.article.category.list',
    'official.article.category.add',
    'official.article.list',
    'official.article.add',
    'official.article.delete',
] as $permission) {
    officialArticleExpect(in_array($permission, $permissionKeys, true), 'missing Article permission: ' . $permission);
}
officialArticleExpect(
    count(array_filter($permissionKeys, static fn(string $key): bool => str_starts_with($key, 'official.article.'))) === count($permissionKeys),
    'official Article permission escaped its Module-key namespace'
);

$routes = (string)file_get_contents($moduleRoot . '/Http/routes.php');
$hostRoutes = peanut_route_registry_source($serverRoot);
$legacyHostRoutes = implode('', array_map(
    static fn(string $file): string => (string)file_get_contents($serverRoot . '/route/' . $file),
    ['app.php', 'platform.php', 'tenant.php', 'admin.php', 'public_api.php'],
));
$repository = (string)file_get_contents($moduleRoot . '/Infrastructure/Persistence/ArticleTenantRepository.php');
$capability = (string)file_get_contents($moduleRoot . '/Application/ArticleCapabilityAuthorization.php');
$publicMiddleware = (string)file_get_contents($serverRoot . '/app/api/middleware/PublicTenantModuleMiddleware.php');
$administration = (string)file_get_contents($moduleRoot . '/Application/ArticleAdministrationService.php');
$provider = (string)file_get_contents($moduleRoot . '/ModuleProvider.php');
$categoryController = (string)file_get_contents($moduleRoot . '/Http/Controller/ArticleCateController.php');
$menuLogic = (string)file_get_contents($serverRoot . '/app/adminapi/application/auth/MenuApplicationService.php');
$permissionService = (string)file_get_contents($serverRoot . '/app/common/service/authorization/AdminAuthorizationService.php');
officialArticleExpect(substr_count($routes, "Route::get('official.article.") === 5, 'Article GET route count changed');
officialArticleExpect(substr_count($routes, "Route::post('official.article.") === 8, 'Article POST route count changed');
officialArticleExpect(
    str_contains($routes, 'OfficialModuleMiddleware::class')
        && str_contains($routes, "(new ModuleProvider())->moduleKey()"),
    'Article routes lost the shared Module execution boundary',
);
officialArticleExpect(
    str_contains($categoryController, 'categoryLists(')
        && str_contains($categoryController, 'categoryDetail(')
        && str_contains($categoryController, 'addCategory(')
        && str_contains($categoryController, 'editCategory(')
        && str_contains($categoryController, 'deleteCategory(')
        && str_contains($categoryController, 'updateCategoryStatus('),
    'Article category controller is not mapped to category application use cases',
);
officialArticleExpect(
    !str_contains($administration, 'class ArticleAdministrationService extends BaseLogic')
        && str_contains($administration, 'implements ArticleAdministration'),
    'Article administration did not converge on its application contract',
);
officialArticleExpect(
    !is_file($moduleRoot . '/Http/ArticleModuleMiddleware.php'),
    'Article-specific Module middleware was reintroduced',
);
officialArticleExpect(!str_contains($legacyHostRoutes, "Route::get('official.article."), 'Article Admin routes remain Host-owned');
officialArticleExpect(!str_contains($legacyHostRoutes, "Route::post('official.article."), 'Article Admin writes remain Host-owned');
officialArticleExpect(
    str_contains($menuLogic, 'CoreTenantModuleAdminBridge::officialModuleMenuPaths')
        && str_contains($permissionService, 'CoreTenantModuleAdminBridge::officialModuleMenuPaths'),
    'legacy Article menu group is not excluded through the shared Module catalog bridge'
);

// Every public Article/PC entry must fail closed when the Tenant Module is disabled.
$publicRoutes = $hostRoutes;
foreach ([
    "Route::get('index/index'",
    "Route::get('article/cate'",
    "Route::get('article/lists'",
    "Route::get('article/detail'",
    "Route::get('pc/index'",
    "Route::get('pc/infoCenter'",
    "Route::get('pc/articleDetail'",
] as $entry) {
    officialArticleExpect(substr_count($publicRoutes, $entry) === 1, 'missing public Article entry: ' . $entry);
}
officialArticleExpect(
    substr_count($publicRoutes, "->middleware(PublicTenantModuleMiddleware::class, 'peanut.article.public-read', 'official.article'") === 7,
    'public Article and PC aggregation entries are not uniformly Module guarded'
);

$pcController = (string)file_get_contents($serverRoot . '/app/api/controller/PcController.php');
$pcApplication = (string)file_get_contents($serverRoot . '/app/api/application/PcApplicationService.php');
$articleApplication = (string)file_get_contents($serverRoot . '/app/api/application/ArticleApplicationService.php');
$userApplication = (string)file_get_contents($serverRoot . '/app/api/application/UserApplicationService.php');
officialArticleExpect(
    str_contains($pcController, "publicTenantContext('article.pc-index')")
        && str_contains($pcController, "publicTenantContext('article.info-center')")
        && str_contains($pcController, "publicTenantContext('article.pc-detail')"),
    'PC article/detail aggregation lost the injected public Tenant context'
);
officialArticleExpect(
    str_contains($pcApplication, 'private readonly ArticleApplicationService $articles')
        && substr_count($pcApplication, '$this->articles->limitArticles(') === 3
        && !str_contains($pcApplication, 'app(ArticleApplicationService::class)')
        && str_contains($pcApplication, 'pageByType('),
    'PC aggregation no longer routes Article and decoration reads through guarded services'
);
officialArticleExpect(
    substr_count($articleApplication, 'ArticleTenantRepository::collections(') >= 4,
    'member collection path lost Article-owned storage boundary'
);
officialArticleExpect(
    str_contains($provider, 'ArticleCollectionSummary::class =>')
        && str_contains($userApplication, 'private readonly ArticleCollectionSummary $articleCollections')
        && str_contains($userApplication, '$this->articleCollections->countForMember(')
        && !str_contains($userApplication, 'ArticleModuleProvider')
        && str_contains($userApplication, 'catch (ModuleException)')
        && !str_contains($userApplication, 'ArticleCollect::'),
    'member center bypasses the public Article collection summary contract'
);
officialArticleExpect(
    str_contains($publicMiddleware, "'文章模块当前不可用'")
        && str_contains($publicMiddleware, '\'error_code\' => $exception->errorCode'),
    'public Article disable refusal is not fail-closed with a stable error code'
);

officialArticleExpect(
    !str_contains($repository, 'ModuleProvider')
        && !str_contains($repository, 'assertAvailable')
        && !str_contains($repository, "where('tenant_id'")
        && !str_contains($repository, "['tenant_id' =>"),
    'Article repository reintroduced per-query Module or Tenant enforcement',
);
officialArticleExpect(str_contains($capability, 'assertTenant('), 'Article typed target lost TenantModule enforcement');
officialArticleExpect(
    str_contains($publicMiddleware, '$this->entryBindings->system(')
        && str_contains($publicMiddleware, 'assertHttp($moduleKey, $operation)')
        && str_contains($publicRoutes, "'peanut.article.public-read', 'official.article'")
        && str_contains($publicMiddleware, 'ModuleExecutionBoundary'),
    'public Article entry is not Host-bound and Module guarded'
);

$contribution = (string)file_get_contents($repoRoot . '/web/src/modules/official-article/contribution.ts');
officialArticleExpect(
    substr_count($contribution, "tenantModuleKey: 'official.article'") === 3,
    'Article frontend contribution lost TenantModule route metadata'
);
officialArticleExpect(
    !is_file($repoRoot . '/web/src/router/routes/modules/article.ts')
        && str_contains($contribution, "@/modules/official-article/views/cate/index.vue")
        && str_contains($contribution, "@/modules/official-article/views/list/index.vue"),
    'Article frontend route escaped its Module subtree'
);

$moduleFiles = [
    'Http/Controller/AbstractArticleCrudController.php',
    'Http/Controller/ArticleCateController.php',
    'Http/Controller/ArticleController.php',
    'Application/ArticleAdministrationService.php',
    'Contracts/ArticleAdministration.php',
    'Validation/ArticleCateValidate.php',
    'Validation/ArticleValidate.php',
    'Model/Article.php',
    'Model/ArticleCate.php',
    'Model/ArticleCollect.php',
];
foreach ($moduleFiles as $relative) {
    officialArticleExpect(is_file($moduleRoot . '/' . $relative), 'Article business file is outside Module subtree: ' . $relative);
}
foreach ([
    '/app/adminapi/controller/article',
    '/app/adminapi/application/article',
    '/app/adminapi/validate/article',
    '/app/common/model/article',
] as $legacyDirectory) {
    officialArticleExpect(!is_dir($serverRoot . $legacyDirectory), 'legacy Article backend directory remains: ' . $legacyDirectory);
}
officialArticleExpect(
    is_file($repoRoot . '/web/src/modules/official-article/api.ts')
        && is_dir($repoRoot . '/web/src/modules/official-article/views')
        && !is_file($repoRoot . '/web/src/api/article.ts')
        && !is_dir($repoRoot . '/web/src/views/article'),
    'Article frontend business code did not move atomically into its Module subtree'
);

echo "OFFICIAL-ARTICLE-MODULE-001 passed\n";
