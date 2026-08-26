<?php
declare(strict_types=1);

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
$hostRoutes = (string)file_get_contents($serverRoot . '/route/app.php');
$repository = (string)file_get_contents($serverRoot . '/app/common/service/article/ArticleTenantRepository.php');
$capability = (string)file_get_contents($serverRoot . '/app/common/service/capability/ArticleCapabilityAuthorization.php');
$publicMiddleware = (string)file_get_contents($serverRoot . '/app/api/middleware/PublicArticleTenantMiddleware.php');
$menuLogic = (string)file_get_contents($serverRoot . '/app/adminapi/logic/auth/MenuLogic.php');
$permissionService = (string)file_get_contents($serverRoot . '/app/common/service/authorization/AdminAuthorizationService.php');
officialArticleExpect(substr_count($routes, "Route::get('official.article.") === 5, 'Article GET route count changed');
officialArticleExpect(substr_count($routes, "Route::post('official.article.") === 8, 'Article POST route count changed');
officialArticleExpect(str_contains($routes, 'ArticleModuleMiddleware::class'), 'Article routes lost ModuleGuard middleware');
officialArticleExpect(!str_contains($hostRoutes, "Route::get('official.article."), 'Article Admin routes remain Host-owned');
officialArticleExpect(!str_contains($hostRoutes, "Route::post('official.article."), 'Article Admin writes remain Host-owned');
officialArticleExpect(
    str_contains($menuLogic, 'CoreTenantModuleAdminBridge::officialModuleMenuPaths')
        && str_contains($permissionService, 'CoreTenantModuleAdminBridge::officialModuleMenuPaths'),
    'legacy Article menu group is not excluded through the shared Module catalog bridge'
);

// Every public Article/PC entry must fail closed when the Tenant Module is disabled.
$publicRoutes = $hostRoutes;
foreach ([
    "Route::get('api/index/index'",
    "Route::get('api/article/cate'",
    "Route::get('api/article/lists'",
    "Route::get('api/article/detail'",
    "Route::get('api/pc/index'",
    "Route::get('api/pc/infoCenter'",
    "Route::get('api/pc/articleDetail'",
] as $entry) {
    officialArticleExpect(substr_count($publicRoutes, $entry) === 1, 'missing public Article entry: ' . $entry);
}
officialArticleExpect(
    substr_count($publicRoutes, '->middleware(PublicArticleTenantMiddleware::class') === 7,
    'public Article and PC aggregation entries are not uniformly Module guarded'
);

$pcController = (string)file_get_contents($serverRoot . '/app/api/controller/PcController.php');
$pcLogic = (string)file_get_contents($serverRoot . '/app/api/logic/PcLogic.php');
$articleLogic = (string)file_get_contents($serverRoot . '/app/api/logic/ArticleLogic.php');
$userLogic = (string)file_get_contents($serverRoot . '/app/api/logic/UserLogic.php');
officialArticleExpect(
    substr_count($pcController, 'ArticleTenantContext::read(') >= 3,
    'PC article/detail aggregation lost Article Tenant context'
);
officialArticleExpect(
    str_contains($pcLogic, 'ArticleLogic::limitArticles($context')
        && str_contains($pcLogic, 'DecorationReadService::pageByType('),
    'PC aggregation no longer routes Article and decoration reads through guarded services'
);
officialArticleExpect(
    substr_count($articleLogic, 'ArticleTenantRepository::collections(') >= 4,
    'member collection path lost Article-owned storage boundary'
);
officialArticleExpect(
    str_contains($userLogic, 'collectionSummary()')
        && str_contains($userLogic, 'catch (ModuleException)')
        && !str_contains($userLogic, 'ArticleCollect::'),
    'member center bypasses the public Article collection summary contract'
);
officialArticleExpect(
    str_contains($publicMiddleware, "JsonService::fail('文章模块当前不可用'")
        && str_contains($publicMiddleware, '\'error_code\' => $exception->errorCode'),
    'public Article disable refusal is not fail-closed with a stable error code'
);

officialArticleExpect(str_contains($repository, 'ModuleProvider'), 'Article repository lost TenantModule enforcement');
officialArticleExpect(str_contains($capability, 'assertTenant('), 'Article typed target lost TenantModule enforcement');
officialArticleExpect(
    str_contains($publicMiddleware, 'TenantEntryBindingResolver::production()->system(')
        && str_contains($publicMiddleware, 'ModuleExecutionContext::system(')
        && str_contains($publicMiddleware, 'PdoModuleGovernanceProvider::forExecution'),
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
    'Service/ArticleCateLogic.php',
    'Service/ArticleLogic.php',
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
    '/app/adminapi/logic/article',
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
