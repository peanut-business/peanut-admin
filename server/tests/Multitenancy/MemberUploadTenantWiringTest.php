<?php
declare(strict_types=1);

use app\api\controller\UploadController;
use app\common\enum\FileEnum;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;
use think\file\UploadedFile;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require __DIR__ . '/../Support/IsolatedBackendEnvironment.php';

function expectMemberUpload(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function memberUploadContext(int $tenantId, int $memberId, string $requestId): TenantContext
{
    return TenantContext::fromValidatedSession(new ValidatedTenantSession(
        $memberId,
        '01JMT03UPLOAD' . str_pad((string)$memberId, 13, '0', STR_PAD_LEFT),
        $tenantId,
        $memberId + 10000,
        $memberId,
        'member-web',
        new DateTimeImmutable('2031-01-01T00:00:00Z'),
        1,
    ), $requestId);
}

$serverRoot = dirname(__DIR__, 2);
$routeSource = (string)file_get_contents($serverRoot . '/app/Modules/Official/File/Http/routes.php');
$uploadRoute = "Route::post('api/upload/image', [ApiUploadController::class, 'image'])";
expectMemberUpload(substr_count($routeSource, $uploadRoute) === 1, 'member upload route is missing or duplicated');
expectMemberUpload(
    str_contains(
        $routeSource,
        $uploadRoute . "\n    ->middleware(CheckTokenMiddleware::class)\n    ->middleware(OfficialModuleMiddleware::class",
    ),
    'member upload route is not protected by identity and Module middleware'
);

$host = IsolatedBackendEnvironment::required('DB_HOST');
$port = (int)IsolatedBackendEnvironment::required('DB_PORT');
$user = IsolatedBackendEnvironment::required('DB_USER');
$password = IsolatedBackendEnvironment::required('DB_PASS');
$runId = strtolower(bin2hex(random_bytes(5)));
$database = 'peanut_admin_mt03_member_upload_' . $runId;
$admin = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    $user,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$storedObject = null;
$temporaryUpload = tempnam(sys_get_temp_dir(), 'peanut-member-upload-');

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec(<<<'SQL'
CREATE TABLE pa_config (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  type VARCHAR(32) NOT NULL DEFAULT '',
  name VARCHAR(64) NOT NULL DEFAULT '',
  value TEXT NULL,
  create_time INT UNSIGNED NOT NULL DEFAULT 0,
  update_time INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id), UNIQUE KEY uk_type_name (type, name)
) ENGINE=InnoDB;
CREATE TABLE pa_file_cate (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  pid INT UNSIGNED NOT NULL DEFAULT 0,
  type TINYINT NOT NULL DEFAULT 10,
  name VARCHAR(64) NOT NULL DEFAULT '',
  create_time INT UNSIGNED NOT NULL DEFAULT 0,
  update_time INT UNSIGNED NOT NULL DEFAULT 0,
  delete_time INT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (id), UNIQUE KEY uk_file_cate_tenant_id (tenant_id, id)
) ENGINE=InnoDB;
CREATE TABLE pa_file (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  cid INT UNSIGNED NOT NULL DEFAULT 0,
  source_id INT UNSIGNED NOT NULL DEFAULT 0,
  source TINYINT NOT NULL DEFAULT 0,
  type TINYINT NOT NULL DEFAULT 10,
  name VARCHAR(255) NOT NULL DEFAULT '',
  uri VARCHAR(255) NOT NULL DEFAULT '',
  storage VARCHAR(20) NOT NULL DEFAULT 'local',
  create_time INT UNSIGNED NOT NULL DEFAULT 0,
  update_time INT UNSIGNED NOT NULL DEFAULT 0,
  delete_time INT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (id), UNIQUE KEY uk_file_tenant_id (tenant_id, id)
) ENGINE=InnoDB;
INSERT INTO pa_config (type, name, value) VALUES ('storage', 'default', 'local');
SQL);

    IsolatedBackendEnvironment::activateDatabase($host, $port, $database, $user, $password);

    $app = new think\App($serverRoot);
    $app->initialize();
    $request = $app->request;
    $request->tenantContext = memberUploadContext(101, 501, 'mt03-member-upload-' . $runId);
    $request->memberInfo = ['id' => 501];
    $request->withPost([
        'cid' => 0,
        'tenant_id' => 202,
        'source_id' => 999,
        'source' => FileEnum::SOURCE_ADMIN,
        'member_id' => 999,
    ]);

    expectMemberUpload($temporaryUpload !== false, 'could not allocate upload fixture');
    file_put_contents(
        $temporaryUpload,
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true)
    );
    $request->withFiles([
        'file' => new UploadedFile($temporaryUpload, 'member-avatar.png', 'image/png', UPLOAD_ERR_OK, true),
    ]);
    $app->instance('request', $request);

    $response = (new UploadController($app))->image();
    $body = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    expectMemberUpload(($body['code'] ?? null) === 20000, 'member upload failed: ' . ($body['msg'] ?? 'unknown error'));

    $row = $pdo->query('SELECT tenant_id, source_id, source, type, name, uri, storage FROM pa_file LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    expectMemberUpload(is_array($row), 'member upload did not create a file row');
    expectMemberUpload((int)$row['tenant_id'] === 101, 'payload forged uploaded file Tenant ownership');
    expectMemberUpload((int)$row['source_id'] === 501, 'payload forged uploaded file member owner');
    expectMemberUpload((int)$row['source'] === FileEnum::SOURCE_USER, 'member upload was stored as an admin upload');
    expectMemberUpload((int)$row['type'] === FileEnum::IMAGE, 'member upload file type changed');
    expectMemberUpload($row['name'] === 'member-avatar.png', 'member upload original name changed');
    expectMemberUpload(str_starts_with($row['uri'], 'storage/tenants/v1/101/uploads/images/'), 'member upload escaped its Tenant object namespace');
    expectMemberUpload(!str_contains($row['uri'], '/202/'), 'payload Tenant appeared in stored object namespace');
    expectMemberUpload($row['storage'] === 'local', 'member upload did not use the configured local storage');

    $storedObject = $serverRoot . '/public/' . $row['uri'];
    expectMemberUpload(is_file($storedObject), 'member upload object was not written to storage');
    expectMemberUpload((int)$pdo->query('SELECT COUNT(*) FROM pa_file')->fetchColumn() === 1, 'member upload created an unexpected number of rows');

    echo "MT03-MEMBER-UPLOAD-TENANT-WIRING-001 passed\n";
} finally {
    if (is_string($storedObject) && is_file($storedObject)) {
        unlink($storedObject);
    }
    if (is_string($temporaryUpload) && is_file($temporaryUpload)) {
        unlink($temporaryUpload);
    }
    if (isset($row['uri'])) {
        $directory = dirname($serverRoot . '/public/' . $row['uri']);
        @rmdir($directory);
        @rmdir(dirname($directory));
        @rmdir(dirname($directory, 2));
        @rmdir(dirname($directory, 3));
    }
    $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
}
