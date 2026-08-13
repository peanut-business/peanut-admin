<?php
declare(strict_types=1);

use app\common\service\tenant\DefaultTenantBootstrap;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require dirname(__DIR__, 2) . '/database/install.php';

function mt02Expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function mt02AdminConnection(): PDO
{
    $host = getenv('MYSQL_HOST') ?: (getenv('DB_HOST') ?: '127.0.0.1');
    $port = getenv('MYSQL_PORT') ?: (getenv('DB_PORT') ?: '33463');
    $password = getenv('MYSQL_ROOT_PASSWORD') ?: (getenv('DB_PASS') ?: 'peanut_admin_root_dev');
    return new PDO(
        "mysql:host={$host};port={$port};charset=utf8mb4",
        'root',
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
}

function mt02Database(PDO $admin, string $database): PDO
{
    $admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
    $host = getenv('MYSQL_HOST') ?: (getenv('DB_HOST') ?: '127.0.0.1');
    $port = getenv('MYSQL_PORT') ?: (getenv('DB_PORT') ?: '33463');
    $password = getenv('MYSQL_ROOT_PASSWORD') ?: (getenv('DB_PASS') ?: 'peanut_admin_root_dev');
    return new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        'root',
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
        ]
    );
}

function mt02Sql(PDO $pdo, string $path, string $ownerPassword): void
{
    $sql = file_get_contents($path);
    mt02Expect(is_string($sql), 'fixture SQL must be readable');
    if (basename($path) === 'init.sql') {
        $sql = replaceInitialAdminSeed($sql, $ownerPassword, '0123456789abcdef');
    }
    $pdo->exec($sql);
}

/** @return array<string,int> */
function mt02Counts(PDO $pdo): array
{
    $tables = ['pa_tenant', 'pa_account', 'pa_credential', 'pa_tenant_member', 'pa_role', 'pa_department',
        'pa_member_role', 'pa_legacy_admin_tenant_map', 'pa_legacy_role_tenant_map', 'pa_legacy_dept_tenant_map'];
    $counts = [];
    foreach ($tables as $table) {
        $counts[$table] = (int)$pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    }
    return $counts;
}

function mt02PrepareLegacy(PDO $pdo, string $serverDir, string $password, bool $fixture): void
{
    mt02Sql($pdo, $serverDir . '/database/init.sql', $password);
    foreach (glob($serverDir . '/database/migrations/*.sql') ?: [] as $file) {
        if (basename($file) >= DefaultTenantBootstrap::MIGRATION) {
            continue;
        }
        mt02Sql($pdo, $file, $password);
    }
    if ($fixture) {
        mt02Sql($pdo, $serverDir . '/tests/fixtures/mt02/default-tenant-upgrade.sql', $password);
    }
}

function mt02Run(PDO $pdo, string $serverDir, string $email, string $password): array
{
    $bootstrap = new DefaultTenantBootstrap($pdo);
    $prepared = $bootstrap->prepare($email, $password);
    if ($prepared['status'] !== 'already_bootstrapped') {
        mt02Sql($pdo, $serverDir . '/database/migrations/' . DefaultTenantBootstrap::MIGRATION, $password);
    }
    return $bootstrap->complete();
}

$serverDir = dirname(__DIR__, 2);
$admin = mt02AdminConnection();
$run = strtolower(bin2hex(random_bytes(5)));
$databases = [];
$ownerEmail = 'owner+' . $run . '@example.test';
$ownerPassword = 'OwnerPassword2026';

try {
    $freshName = 'pa_mt02_fresh_' . $run;
    $databases[] = $freshName;
    $fresh = mt02Database($admin, $freshName);
    putenv('DB_HOST=' . (getenv('MYSQL_HOST') ?: (getenv('DB_HOST') ?: '127.0.0.1')));
    putenv('DB_PORT=' . (getenv('MYSQL_PORT') ?: (getenv('DB_PORT') ?: '33463')));
    putenv('DB_NAME=' . $freshName);
    putenv('DB_USER=root');
    putenv('DB_PASS=' . (getenv('MYSQL_ROOT_PASSWORD') ?: (getenv('DB_PASS') ?: 'peanut_admin_root_dev')));
    putenv('ADMIN_INITIAL_EMAIL=' . $ownerEmail);
    putenv('ADMIN_INITIAL_PASSWORD=' . $ownerPassword);
    ob_start();
    $installCode = main();
    ob_end_clean();
    mt02Expect($installCode === 0, 'fresh installer must complete');
    mt02Expect((int)$fresh->query("SELECT COUNT(*) FROM pa_tenant WHERE code='default' AND status='active'")->fetchColumn() === 1, 'fresh default tenant missing');
    mt02Expect((int)$fresh->query("SELECT COUNT(*) FROM pa_legacy_admin_tenant_map")->fetchColumn() === 1, 'fresh root mapping missing');

    $upgradeName = 'pa_mt02_upgrade_' . $run;
    $databases[] = $upgradeName;
    $upgrade = mt02Database($admin, $upgradeName);
    mt02PrepareLegacy($upgrade, $serverDir, $ownerPassword, true);
    $legacyCounts = [
        'admin' => (int)$upgrade->query('SELECT COUNT(*) FROM pa_admin')->fetchColumn(),
        'role' => (int)$upgrade->query('SELECT COUNT(*) FROM pa_system_role')->fetchColumn(),
        'dept' => (int)$upgrade->query('SELECT COUNT(*) FROM pa_dept')->fetchColumn(),
        'job' => (int)$upgrade->query('SELECT COUNT(*) FROM pa_jobs')->fetchColumn(),
    ];
    mt02Run($upgrade, $serverDir, $ownerEmail, $ownerPassword);
    mt02Expect((int)$upgrade->query('SELECT COUNT(*) FROM pa_legacy_admin_tenant_map')->fetchColumn() === $legacyCounts['admin'], 'all admins must map');
    mt02Expect((int)$upgrade->query('SELECT COUNT(*) FROM pa_legacy_role_tenant_map')->fetchColumn() === $legacyCounts['role'], 'all roles must map');
    mt02Expect((int)$upgrade->query('SELECT COUNT(*) FROM pa_legacy_dept_tenant_map')->fetchColumn() === $legacyCounts['dept'], 'all departments must map');
    mt02Expect((int)$upgrade->query('SELECT COUNT(*) FROM pa_jobs WHERE tenant_id IS NULL')->fetchColumn() === 0, 'jobs must have tenant ownership');
    mt02Expect((int)$upgrade->query("SELECT COUNT(*) FROM pa_tenant_member WHERE status='suspended'")->fetchColumn() === 1, 'disabled admin must map suspended');
    mt02Expect((int)$upgrade->query("SELECT COUNT(*) FROM pa_department WHERE code='legacy.dept.2' AND parent_id IS NOT NULL")->fetchColumn() === 1, 'department hierarchy must map');
    $beforeReplay = mt02Counts($upgrade);
    $replay = mt02Run($upgrade, $serverDir, $ownerEmail, $ownerPassword);
    mt02Expect($replay['status'] === 'already_bootstrapped', 'replay must be idempotent');
    mt02Expect(mt02Counts($upgrade) === $beforeReplay, 'replay must not duplicate rows');

    foreach (['missing_email', 'bad_password', 'second_root', 'orphan', 'cycle'] as $case) {
        $name = 'pa_mt02_fail_' . $case . '_' . $run;
        $databases[] = $name;
        $pdo = mt02Database($admin, $name);
        mt02PrepareLegacy($pdo, $serverDir, $ownerPassword, true);
        if ($case === 'second_root') {
            $pdo->exec("UPDATE pa_admin SET root=1 WHERE id=2");
        } elseif ($case === 'orphan') {
            $pdo->exec("SET FOREIGN_KEY_CHECKS=0; INSERT INTO pa_admin_role(admin_id,role_id) VALUES(999999,1); SET FOREIGN_KEY_CHECKS=1");
        } elseif ($case === 'cycle') {
            $pdo->exec("UPDATE pa_dept SET pid=2 WHERE id=1");
        }
        $email = $case === 'missing_email' ? '' : $ownerEmail;
        $password = $case === 'bad_password' ? 'WrongPassword2026' : $ownerPassword;
        $failed = false;
        try {
            (new DefaultTenantBootstrap($pdo))->prepare($email, $password);
        } catch (Throwable $exception) {
            $failed = true;
            mt02Expect(!str_contains($exception->getMessage(), $ownerPassword), 'failure must not expose password');
        }
        mt02Expect($failed, "{$case} must fail closed");
        $coreRows = 0;
        foreach (['pa_tenant', 'pa_account', 'pa_tenant_member'] as $table) {
            $exists = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='{$table}'")->fetchColumn();
            $coreRows += $exists === 1 ? (int)$pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn() : 0;
        }
        mt02Expect($coreRows === 0, "{$case} must fail before Core mapping writes");
    }

    echo "MT02-BOOTSTRAP-001 passed\n";
} finally {
    foreach ($databases as $database) {
        $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
    }
}
