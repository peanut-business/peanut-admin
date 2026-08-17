<?php
declare(strict_types=1);

use app\adminapi\logic\article\ArticleCateLogic as AdminArticleCateLogic;
use app\adminapi\logic\article\ArticleLogic as AdminArticleLogic;
use app\api\logic\ArticleLogic as ApiArticleLogic;
use app\common\service\article\ArticleTenantContext;
use app\common\service\article\ArticleTenantRepository;
use app\common\service\capability\ArticleCapabilityAuthorization;
use app\common\service\decoration\DecorationSchemaService;
use app\common\service\member\AuthenticatedMemberContext;
use PeanutAdmin\Kernel\Api\ApiException;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function expectArticleTenant(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function tenantContext(int $tenantId, int $accountId, int $memberId, string $requestId): TenantContext
{
    return TenantContext::fromValidatedSession(new ValidatedTenantSession(
        $memberId,
        '01JMT02ARTICLE' . str_pad((string)$memberId, 13, '0', STR_PAD_LEFT),
        $tenantId,
        $accountId,
        $memberId,
        'admin-web',
        new DateTimeImmutable('2031-01-01T00:00:00Z'),
        1,
    ), $requestId);
}

function deniedShape(callable $operation): array
{
    try {
        $operation();
    } catch (ApiException $exception) {
        return [$exception->errorCode, $exception->httpStatus, $exception->getMessage()];
    }
    throw new RuntimeException('Article capability denial was expected.');
}

function createArticleCollectMemberFkSchema(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
CREATE TABLE pa_tenant (
  id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(32) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB;
CREATE TABLE pa_member (
  id INT UNSIGNED NOT NULL,
  tenant_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_member_tenant_id (tenant_id, id),
  CONSTRAINT fk_collect_gate_member_tenant FOREIGN KEY (tenant_id) REFERENCES pa_tenant (id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE pa_article (
  id INT UNSIGNED NOT NULL,
  tenant_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_article_tenant_id (tenant_id, id),
  CONSTRAINT fk_collect_gate_article_tenant FOREIGN KEY (tenant_id) REFERENCES pa_tenant (id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE pa_article_collect (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  member_id INT UNSIGNED NOT NULL,
  article_id INT UNSIGNED NOT NULL,
  status TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uk_article_collect_tenant_member_article (tenant_id, member_id, article_id),
  CONSTRAINT fk_article_collect_tenant FOREIGN KEY (tenant_id) REFERENCES pa_tenant (id) ON DELETE RESTRICT,
  CONSTRAINT fk_article_collect_tenant_article FOREIGN KEY (tenant_id, article_id) REFERENCES pa_article (tenant_id, id) ON DELETE RESTRICT,
  CONSTRAINT fk_article_collect_tenant_member FOREIGN KEY (tenant_id, member_id) REFERENCES pa_member (tenant_id, id) ON DELETE RESTRICT
) ENGINE=InnoDB;
INSERT INTO pa_tenant (id, status) VALUES (101, 'active'), (202, 'active');
INSERT INTO pa_member (id, tenant_id) VALUES (501, 101), (502, 202);
INSERT INTO pa_article (id, tenant_id) VALUES (21, 101), (22, 202);
SQL);
}

function expectArticleCollectConstraintFailure(PDO $pdo, string $sql, string $message): void
{
    try {
        $pdo->exec($sql);
    } catch (PDOException $exception) {
        expectArticleTenant($exception->getCode() === '23000', $message . ': unexpected SQLSTATE ' . $exception->getCode());
        return;
    }
    throw new RuntimeException($message . ': insert unexpectedly succeeded');
}

function runArticleCollectMemberFkGate(): void
{
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = (int)(getenv('DB_PORT') ?: 3306);
    $password = getenv('MYSQL_ROOT_PASSWORD') ?: 'mt02_root';
    $runId = strtolower(bin2hex(random_bytes(5)));
    $database = 'peanut_mt02_collect_member_' . $runId;
    $admin = new PDO(
        "mysql:host={$host};port={$port};charset=utf8mb4",
        'root',
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_MULTI_STATEMENTS => true]
    );
    try {
        $admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
            'root',
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false, PDO::MYSQL_ATTR_MULTI_STATEMENTS => true]
        );
        createArticleCollectMemberFkSchema($pdo);
        $pdo->exec(
            'INSERT INTO pa_article_collect (tenant_id, member_id, article_id) VALUES (101, 501, 21)'
        );
        expectArticleCollectConstraintFailure(
            $pdo,
            'INSERT INTO pa_article_collect (tenant_id, member_id, article_id) VALUES (101, 502, 21)',
            'cross-Tenant member collection was not rejected'
        );
        expectArticleCollectConstraintFailure(
            $pdo,
            'INSERT INTO pa_article_collect (tenant_id, member_id, article_id) VALUES (101, 501, 22)',
            'cross-Tenant Article collection was not rejected'
        );
        expectArticleTenant(
            (int)$pdo->query(
                'SELECT COUNT(*) FROM pa_article_collect WHERE tenant_id=101 AND member_id=501 AND article_id=21'
            )->fetchColumn() === 1,
            'existing valid Article collection changed after migration'
        );

        echo "MT02-ARTICLE-COLLECT-MEMBER-TENANT-FK-001 passed\n";
    } finally {
        $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
    }
}

if (in_array('--collect-member-fk', $argv ?? [], true)) {
    runArticleCollectMemberFkGate();
    exit(0);
}

$serverRoot = dirname(__DIR__, 2);
foreach ([
    'app/common/service/article/ArticleTenantContext.php',
    'app/common/service/article/ArticleTenantRepository.php',
    'app/common/service/capability/ArticleCapabilityAuthorization.php',
    'app/common/model/article/Article.php',
    'app/common/model/article/ArticleCate.php',
    'app/common/model/article/ArticleCollect.php',
    'app/adminapi/controller/article/ArticleController.php',
    'app/adminapi/controller/article/ArticleCateController.php',
    'app/adminapi/logic/article/ArticleLogic.php',
    'app/adminapi/logic/article/ArticleCateLogic.php',
    'app/adminapi/validate/article/ArticleValidate.php',
    'app/adminapi/validate/article/ArticleCateValidate.php',
    'app/adminapi/controller/decoration/DecorationPageController.php',
    'app/adminapi/controller/decoration/DecorationTabbarController.php',
    'app/adminapi/logic/decoration/DecorationPageLogic.php',
    'app/adminapi/logic/decoration/DecorationTabbarLogic.php',
    'app/common/service/decoration/DecorationSchemaService.php',
    'app/api/controller/ArticleController.php',
    'app/api/controller/IndexController.php',
    'app/api/controller/PcController.php',
    'app/api/controller/UserController.php',
    'app/api/logic/ArticleLogic.php',
    'app/api/logic/IndexLogic.php',
    'app/api/logic/PcLogic.php',
    'app/api/logic/UserLogic.php',
    'tests/Productization/ContentDecorationHostTest.php',
    'tests/Multitenancy/ArticleTenantIsolationTest.php',
] as $relativePath) {
    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($serverRoot . '/' . $relativePath);
    exec($command, $lintOutput, $lintExit);
    expectArticleTenant($lintExit === 0, 'PHP 8.3 lint failed: ' . $relativePath . ' ' . implode(' ', $lintOutput));
    $lintOutput = [];
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = (int)(getenv('DB_PORT') ?: 3306);
$password = getenv('MYSQL_ROOT_PASSWORD') ?: 'mt02_root';
$database = 'peanut_admin_mt02_article_' . strtolower(bin2hex(random_bytes(5)));
$admin = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    'root',
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_MULTI_STATEMENTS => true]
);
$admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        'root',
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false, PDO::MYSQL_ATTR_MULTI_STATEMENTS => true]
    );
    $pdo->exec(<<<'SQL'
CREATE TABLE pa_tenant (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  status VARCHAR(32) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB;
CREATE TABLE pa_member (
  id INT UNSIGNED NOT NULL,
  tenant_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (id), UNIQUE KEY uk_member_tenant_id (tenant_id, id),
  CONSTRAINT fk_article_member_tenant FOREIGN KEY (tenant_id) REFERENCES pa_tenant (id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE pa_article_cate (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(90) NOT NULL DEFAULT '', sort INT NOT NULL DEFAULT 0,
  is_show TINYINT UNSIGNED NOT NULL DEFAULT 1,
  create_time INT UNSIGNED NOT NULL DEFAULT 0, update_time INT UNSIGNED NOT NULL DEFAULT 0,
  delete_time INT UNSIGNED NULL DEFAULT NULL,
  tenant_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (id), UNIQUE KEY uk_article_cate_tenant_id (tenant_id, id),
  KEY idx_article_cate_tenant_visible (tenant_id, is_show, sort, id),
  CONSTRAINT fk_article_cate_tenant FOREIGN KEY (tenant_id) REFERENCES pa_tenant (id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE pa_article (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, cid INT UNSIGNED NOT NULL DEFAULT 0,
  title VARCHAR(255) NOT NULL DEFAULT '', `desc` VARCHAR(255) NULL DEFAULT '', abstract TEXT NULL,
  image VARCHAR(2048) NULL, author VARCHAR(255) NULL DEFAULT '', content TEXT NULL,
  click_virtual INT NULL DEFAULT 0, click_actual INT NULL DEFAULT 0, sort INT NULL DEFAULT 0,
  is_show TINYINT UNSIGNED NOT NULL DEFAULT 1,
  create_time INT UNSIGNED NOT NULL DEFAULT 0, update_time INT UNSIGNED NOT NULL DEFAULT 0,
  delete_time INT UNSIGNED NULL DEFAULT NULL,
  tenant_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (id), KEY idx_cid (cid), UNIQUE KEY uk_article_tenant_id (tenant_id, id),
  KEY idx_article_tenant_visible_cate (tenant_id, is_show, cid, sort, id),
  CONSTRAINT fk_article_tenant FOREIGN KEY (tenant_id) REFERENCES pa_tenant (id) ON DELETE RESTRICT,
  CONSTRAINT fk_article_tenant_cate FOREIGN KEY (tenant_id, cid) REFERENCES pa_article_cate (tenant_id, id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE pa_article_collect (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, member_id INT UNSIGNED NOT NULL DEFAULT 0,
  article_id INT UNSIGNED NOT NULL DEFAULT 0, status TINYINT UNSIGNED NOT NULL DEFAULT 0,
  create_time INT UNSIGNED NOT NULL DEFAULT 0, update_time INT UNSIGNED NOT NULL DEFAULT 0,
  delete_time INT NULL DEFAULT NULL,
  tenant_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (id), KEY idx_member_id (member_id),
  UNIQUE KEY uk_article_collect_tenant_member_article (tenant_id, member_id, article_id),
  KEY idx_article_collect_tenant_member_status (tenant_id, member_id, status, id),
  CONSTRAINT fk_article_collect_tenant FOREIGN KEY (tenant_id) REFERENCES pa_tenant (id) ON DELETE RESTRICT,
  CONSTRAINT fk_article_collect_tenant_article FOREIGN KEY (tenant_id, article_id) REFERENCES pa_article (tenant_id, id) ON DELETE RESTRICT,
  CONSTRAINT fk_article_collect_tenant_member FOREIGN KEY (tenant_id, member_id) REFERENCES pa_member (tenant_id, id) ON DELETE RESTRICT
) ENGINE=InnoDB;
INSERT INTO pa_tenant (id, status) VALUES (101, 'active'), (202, 'active');
INSERT INTO pa_member (id, tenant_id) VALUES (501, 101), (502, 202);
INSERT INTO pa_article_cate (id, tenant_id, name, sort, is_show) VALUES (11, 101, 'Alpha seed', 10, 1), (12, 202, 'Beta', 10, 1);
INSERT INTO pa_article (id, tenant_id, cid, title, is_show, click_actual) VALUES (21, 101, 11, 'Alpha seed article', 1, 0), (22, 202, 12, 'Beta visible', 1, 0);
INSERT INTO pa_article_collect (id, tenant_id, member_id, article_id, status) VALUES (31, 101, 501, 21, 1);
SQL);

    putenv('PHP_DB_HOST=' . $host);
    putenv('PHP_DB_PORT=' . $port);
    putenv('PHP_DB_NAME=' . $database);
    putenv('PHP_DB_USER=root');
    putenv('PHP_DB_PASS=' . $password);
    putenv('PHP_DB_PREFIX=pa_');
    $app = new think\App();
    $app->initialize();

    $alpha = tenantContext(101, 1001, 501, 'mt02-alpha');
    $beta = tenantContext(202, 2002, 502, 'mt02-beta');
    $alphaMember = new AuthenticatedMemberContext(101, 501, 'fixture-alpha-member', 'mt02-alpha-member');
    $missingRequest = new stdClass();
    try {
        ArticleTenantContext::member($missingRequest);
        throw new RuntimeException('missing TenantContext unexpectedly succeeded');
    } catch (Throwable $exception) {
        expectArticleTenant($exception->getMessage() !== '', 'missing context denial lost its shape');
    }

    $payload = ['tenant_id' => 202, 'name' => 'Alpha category', 'sort' => 20, 'is_show' => 1];
    expectArticleTenant(AdminArticleCateLogic::add($alpha, $payload), AdminArticleCateLogic::getError());
    $alphaCategoryId = (int)ArticleTenantRepository::categories($alpha)->where('name', 'Alpha category')->value('id');
    expectArticleTenant($alphaCategoryId > 0, 'Alpha category was not created');
    expectArticleTenant(
        (int)$pdo->query("SELECT tenant_id FROM pa_article_cate WHERE id = {$alphaCategoryId}")->fetchColumn() === 101,
        'payload tenant_id overrode trusted context'
    );
    expectArticleTenant(AdminArticleLogic::add($alpha, [
        'tenant_id' => 202, 'cid' => $alphaCategoryId, 'title' => 'Alpha visible',
        'is_show' => 1, 'desc' => '', 'abstract' => '', 'content' => '',
    ]), AdminArticleLogic::getError());
    $alphaArticleId = (int)ArticleTenantRepository::articles($alpha)->where('title', 'Alpha visible')->value('id');
    expectArticleTenant($alphaArticleId > 0, 'Alpha article was not created');

    $beforeBeta = $pdo->query('SELECT title, click_actual FROM pa_article WHERE id = 22')->fetch(PDO::FETCH_ASSOC);
    $beforeCollects = (int)$pdo->query('SELECT COUNT(*) FROM pa_article_collect WHERE tenant_id = 202')->fetchColumn();
    expectArticleTenant(ApiArticleLogic::detail($alpha, 22, 501) === [], 'cross-tenant detail enumerated Beta Article');
    expectArticleTenant(ApiArticleLogic::detail($alpha, 999999, 501) === [], 'missing detail denial shape changed');

    expectArticleTenant(!AdminArticleLogic::edit($alpha, [
        'id' => 22, 'cid' => $alphaCategoryId, 'title' => 'cross-tenant-write', 'is_show' => 1,
    ]), 'cross-tenant Article edit unexpectedly succeeded');
    $crossEditError = AdminArticleLogic::getError();
    expectArticleTenant(!AdminArticleLogic::edit($alpha, [
        'id' => 999999, 'cid' => $alphaCategoryId, 'title' => 'missing-write', 'is_show' => 1,
    ]), 'missing Article edit unexpectedly succeeded');
    expectArticleTenant(AdminArticleLogic::getError() === $crossEditError, 'cross-tenant edit enumerated the target');

    expectArticleTenant(!ApiArticleLogic::addCollect($alphaMember, 22, 501), 'cross-tenant collection unexpectedly succeeded');
    $crossCollectError = ApiArticleLogic::getError();
    expectArticleTenant(!ApiArticleLogic::addCollect($alphaMember, 999999, 501), 'missing collection target unexpectedly succeeded');
    expectArticleTenant(ApiArticleLogic::getError() === $crossCollectError, 'cross-tenant collection enumerated the target');

    $link = static fn(int $id): array => ['target_type' => 'article', 'target' => $id];
    foreach ([22, 999999] as $target) {
        try {
            DecorationSchemaService::validateLink($alpha, $link($target));
            throw new RuntimeException('invalid decoration Article unexpectedly succeeded');
        } catch (RuntimeException $exception) {
            expectArticleTenant($exception->getMessage() === '文章链接必须指向存在且可见的文章', 'decoration target enumerated Tenant ownership');
        }
    }

    $authorization = new ArticleCapabilityAuthorization($pdo, static fn(): bool => true);
    $expectedDenied = ['ARTICLE_CAPABILITY_DENIED', 404, 'Article capability is unavailable.'];
    expectArticleTenant(deniedShape(fn() => $authorization->authorizedContext($alpha, '22', 'write')) === $expectedDenied, 'CAP06 adapter exposed cross-tenant Article');
    expectArticleTenant(deniedShape(fn() => $authorization->authorizedContext($alpha, '999999', 'write')) === $expectedDenied, 'CAP06 missing target denial shape changed');
    expectArticleTenant($authorization->authorizedContext($beta, '22', 'write')->tenantContext->tenantId === 202, 'Beta positive typed target failed');

    expectArticleTenant(ApiArticleLogic::addCollect($alphaMember, $alphaArticleId, 501), ApiArticleLogic::getError());
    expectArticleTenant(ApiArticleLogic::detail($alpha, $alphaArticleId, 501)['collect'] === true, 'Alpha Article detail/collection failed');
    expectArticleTenant(count(ApiArticleLogic::lists($alpha, ['page_size' => 20], 501)['lists']) >= 1, 'Alpha list lost visible Article');
    expectArticleTenant(count(ApiArticleLogic::infoCenter($alpha)) >= 1, 'Alpha info center lost categories');
    expectArticleTenant(count(ApiArticleLogic::limitArticles($alpha, 'new', 20)) >= 1, 'Alpha aggregate lost Article');
    DecorationSchemaService::validateLink($alpha, $link($alphaArticleId));
    ApiArticleLogic::cancelCollect($alphaMember, $alphaArticleId, 501);

    expectArticleTenant(
        $pdo->query('SELECT title, click_actual FROM pa_article WHERE id = 22')->fetch(PDO::FETCH_ASSOC) === $beforeBeta,
        'cross-tenant denial changed Beta Article'
    );
    expectArticleTenant(
        (int)$pdo->query('SELECT COUNT(*) FROM pa_article_collect WHERE tenant_id = 202')->fetchColumn() === $beforeCollects,
        'cross-tenant denial changed Beta collections'
    );

    echo json_encode([
        'status' => 'passed',
        'scope' => 'mt02-article-tenant-first',
        'schema' => 'fresh-canonical',
        'tenant_first_denials' => ['detail', 'edit', 'collect', 'decoration', 'typed_target'],
        'permission_policy_allowed' => true,
        'beta_unchanged' => true,
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
}
