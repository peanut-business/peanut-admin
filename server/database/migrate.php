<?php
declare(strict_types=1);

use app\common\service\tenant\DefaultTenantBootstrap;

/**
 * Peanut Admin manual database migration runner.
 *
 * Existing pre-ledger installations run once:
 *     php server/database/migrate.php --adopt-existing
 *
 * Later upgrades run:
 *     php server/database/migrate.php
 */

const MIGRATION_REQUIRED_CONFIG = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS'];
const LEGACY_BASELINE_THROUGH = '20260802_wechat_oauth.sql';
const LEDGER_MIGRATION = '20260807_schema_migration_ledger.sql';

function migrationConfig(string $serverDir): array
{
    $fileConfig = [];
    $envFile = $serverDir . '/.env';
    if (is_file($envFile)) {
        $parsed = parse_ini_file($envFile, false, INI_SCANNER_RAW);
        if ($parsed === false) {
            throw new RuntimeException('无法解析 server/.env');
        }
        $fileConfig = $parsed;
    }

    $config = [];
    foreach (MIGRATION_REQUIRED_CONFIG as $name) {
        $environment = getenv($name);
        $value = $environment !== false && $environment !== ''
            ? $environment
            : ($fileConfig[$name] ?? '');
        if ($value === '') {
            throw new RuntimeException("缺少数据库配置：{$name}");
        }
        $config[$name] = (string)$value;
    }
    return $config;
}

/** @return array<string, mixed> */
function migrationFileConfig(string $serverDir): array
{
    $envFile = $serverDir . '/.env';
    if (!is_file($envFile)) {
        return [];
    }
    $parsed = parse_ini_file($envFile, false, INI_SCANNER_RAW);
    if ($parsed === false) {
        throw new RuntimeException('无法解析 server/.env');
    }
    return $parsed;
}

function initialAdminEmailForMigration(string $serverDir): string
{
    $environment = getenv('ADMIN_INITIAL_EMAIL');
    $email = $environment !== false && $environment !== ''
        ? $environment
        : (migrationFileConfig($serverDir)['ADMIN_INITIAL_EMAIL'] ?? '');
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new RuntimeException('ADMIN_INITIAL_EMAIL 必须是有效邮箱');
    }
    return strtolower((string)$email);
}

function initialAdminPasswordForMigration(string $serverDir): string
{
    $environment = getenv('ADMIN_INITIAL_PASSWORD');
    $password = $environment !== false && $environment !== ''
        ? $environment
        : (migrationFileConfig($serverDir)['ADMIN_INITIAL_PASSWORD'] ?? '');
    if (!is_string($password) || $password === '') {
        throw new RuntimeException('ADMIN_INITIAL_PASSWORD 不能为空');
    }
    return $password;
}

function migrationFiles(string $databaseDir): array
{
    $files = glob($databaseDir . '/migrations/*.sql') ?: [];
    sort($files, SORT_STRING);
    return $files;
}

function migrationConnection(array $config): PDO
{
    return new PDO(
        sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $config['DB_HOST'],
            $config['DB_PORT'],
            $config['DB_NAME']
        ),
        $config['DB_USER'],
        $config['DB_PASS'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
        ]
    );
}

function migrationTableExists(PDO $pdo): bool
{
    return $pdo->query("SHOW TABLES LIKE 'pa_schema_migration'")->fetchColumn() !== false;
}

function migrationSql(string $file): string
{
    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException('无法读取迁移文件：' . basename($file));
    }
    return $sql;
}

function migrationChecksum(string $file): string
{
    $checksum = hash_file('sha256', $file);
    if ($checksum === false) {
        throw new RuntimeException('无法计算迁移校验值：' . basename($file));
    }
    return $checksum;
}

function expectedLegacyTables(string $databaseDir, array $files): array
{
    $sources = [$databaseDir . '/init.sql'];
    foreach ($files as $file) {
        if (basename($file) <= LEGACY_BASELINE_THROUGH) {
            $sources[] = $file;
        }
    }

    $tables = [];
    foreach ($sources as $source) {
        preg_match_all(
            '/CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`([^`]+)`/i',
            migrationSql($source),
            $matches
        );
        foreach ($matches[1] as $table) {
            $tables[$table] = true;
        }
    }
    return array_keys($tables);
}

function assertLegacyInstallation(PDO $pdo, string $databaseDir, array $files): void
{
    $names = array_map('basename', $files);
    if (!in_array(LEGACY_BASELINE_THROUGH, $names, true)) {
        throw new RuntimeException('缺少历史基线迁移：' . LEGACY_BASELINE_THROUGH);
    }

    $actual = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $missing = array_values(array_diff(expectedLegacyTables($databaseDir, $files), $actual));
    $defaultAdmin = in_array('pa_admin', $actual, true)
        ? (int)$pdo->query("SELECT COUNT(*) FROM pa_admin WHERE username = 'admin' AND root = 1")->fetchColumn()
        : 0;
    $menus = in_array('pa_system_menu', $actual, true)
        ? (int)$pdo->query('SELECT COUNT(*) FROM pa_system_menu')->fetchColumn()
        : 0;
    $configs = in_array('pa_config', $actual, true)
        ? (int)$pdo->query('SELECT COUNT(*) FROM pa_config')->fetchColumn()
        : 0;

    if ($missing !== [] || $defaultAdmin !== 1 || $menus === 0 || $configs === 0) {
        throw new RuntimeException('已有数据库不符合历史基线，拒绝接管：' . json_encode([
            'missing_tables' => $missing,
            'default_admin' => $defaultAdmin,
            'menus' => $menus,
            'configs' => $configs,
        ], JSON_UNESCAPED_UNICODE));
    }
}

function recordAppliedMigration(PDO $pdo, string $file, int $batch): void
{
    $statement = $pdo->prepare(
        'INSERT INTO pa_schema_migration '
        . '(migration, checksum, batch, status, started_at, applied_at, error) '
        . "VALUES (?, ?, ?, 'applied', ?, ?, '')"
    );
    $now = time();
    $statement->execute([basename($file), migrationChecksum($file), $batch, $now, $now]);
}

function adoptLegacyInstallation(PDO $pdo, string $databaseDir, array $files): void
{
    if (migrationTableExists($pdo)) {
        if ((int)$pdo->query('SELECT COUNT(*) FROM pa_schema_migration')->fetchColumn() > 0) {
            return;
        }
        assertLegacyInstallation($pdo, $databaseDir, $files);
    } else {
        assertLegacyInstallation($pdo, $databaseDir, $files);
        $ledgerFile = $databaseDir . '/migrations/' . LEDGER_MIGRATION;
        if (!is_file($ledgerFile)) {
            throw new RuntimeException('缺少迁移账本文件：' . LEDGER_MIGRATION);
        }
        $pdo->exec(migrationSql($ledgerFile));
    }

    foreach ($files as $file) {
        $name = basename($file);
        if ($name <= LEGACY_BASELINE_THROUGH || $name === LEDGER_MIGRATION) {
            recordAppliedMigration($pdo, $file, 0);
        }
    }
}

function appliedMigrations(PDO $pdo, array $files): array
{
    $known = [];
    foreach ($files as $file) {
        $known[basename($file)] = $file;
    }

    $rows = $pdo->query(
        'SELECT migration, checksum, status, error FROM pa_schema_migration ORDER BY migration'
    )->fetchAll();
    $applied = [];
    foreach ($rows as $row) {
        $name = (string)$row['migration'];
        if (!isset($known[$name])) {
            throw new RuntimeException('账本中的迁移文件已不存在：' . $name);
        }
        if (!hash_equals((string)$row['checksum'], migrationChecksum($known[$name]))) {
            throw new RuntimeException('迁移文件校验值已变化：' . $name);
        }
        if ((string)$row['status'] !== 'applied') {
            throw new RuntimeException('存在未完成迁移，请人工处理后再继续：' . $name . '；' . $row['error']);
        }
        $applied[$name] = true;
    }
    return $applied;
}

function applyPendingMigrations(
    PDO $pdo,
    array $files,
    ?callable $before = null,
    ?callable $after = null
): array
{
    $applied = appliedMigrations($pdo, $files);
    $pending = array_values(array_filter(
        $files,
        static fn(string $file): bool => !isset($applied[basename($file)])
    ));
    if ($pending === []) {
        return [];
    }

    $batch = (int)$pdo->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM pa_schema_migration')->fetchColumn();
    $start = $pdo->prepare(
        'INSERT INTO pa_schema_migration '
        . '(migration, checksum, batch, status, started_at, applied_at, error) '
        . "VALUES (?, ?, ?, 'running', ?, NULL, '')"
    );
    $finish = $pdo->prepare(
        "UPDATE pa_schema_migration SET status = 'applied', applied_at = ?, error = '' WHERE migration = ?"
    );
    $fail = $pdo->prepare(
        "UPDATE pa_schema_migration SET status = 'failed', error = ? WHERE migration = ?"
    );

    $completed = [];
    foreach ($pending as $file) {
        $name = basename($file);
        try {
            if ($before !== null) {
                $before($name);
            }
            $start->execute([$name, migrationChecksum($file), $batch, time()]);
            $pdo->exec(migrationSql($file));
            if ($after !== null) {
                $after($name);
            }
            $finish->execute([time(), $name]);
            $completed[] = $name;
        } catch (Throwable $exception) {
            $message = substr($exception->getMessage(), 0, 1000);
            $fail->execute([$message, $name]);
            throw new RuntimeException('执行迁移失败：' . $name . '；' . $message, 0, $exception);
        }
    }
    return $completed;
}

function migrateMain(): int
{
    $databaseDir = __DIR__;
    $config = migrationConfig(dirname($databaseDir));
    if (!preg_match('/^[A-Za-z0-9_]+$/', $config['DB_NAME'])) {
        throw new RuntimeException('DB_NAME 只能包含字母、数字和下划线');
    }

    $pdo = migrationConnection($config);
    $lockName = 'peanut_migrate_' . substr(hash('sha256', $config['DB_NAME']), 0, 48);
    $lock = $pdo->prepare('SELECT GET_LOCK(?, 10)');
    $lock->execute([$lockName]);
    if ((int)$lock->fetchColumn() !== 1) {
        throw new RuntimeException('无法获取迁移锁，请稍后重试');
    }

    try {
        $files = migrationFiles($databaseDir);
        if (in_array('--adopt-existing', $_SERVER['argv'] ?? [], true)) {
            adoptLegacyInstallation($pdo, $databaseDir, $files);
        }
        if (!migrationTableExists($pdo)) {
            throw new RuntimeException('迁移账本不存在；历史安装请先执行 --adopt-existing');
        }
        $autoload = dirname($databaseDir) . '/vendor/autoload.php';
        if (!is_file($autoload)) {
            throw new RuntimeException('缺少 Composer autoload，无法执行数据库迁移');
        }
        require_once $autoload;
        $tenantBootstrap = null;
        $completed = applyPendingMigrations(
            $pdo,
            $files,
            static function (string $name) use ($pdo, &$tenantBootstrap): void {
                if ($name !== DefaultTenantBootstrap::MIGRATION) {
                    return;
                }
                $serverDir = dirname(__DIR__);
                $tenantBootstrap = new DefaultTenantBootstrap($pdo);
                $tenantBootstrap->prepare(initialAdminEmailForMigration($serverDir), initialAdminPasswordForMigration($serverDir));
            },
            static function (string $name) use (&$tenantBootstrap): void {
                if ($name === DefaultTenantBootstrap::MIGRATION) {
                    if (!$tenantBootstrap instanceof DefaultTenantBootstrap) {
                        throw new RuntimeException('MT02_BOOTSTRAP_NOT_PREPARED');
                    }
                    $tenantBootstrap->complete();
                }
            }
        );
        $total = (int)$pdo->query(
            "SELECT COUNT(*) FROM pa_schema_migration WHERE status = 'applied'"
        )->fetchColumn();

        echo json_encode([
            'database' => $config['DB_NAME'],
            'status' => $completed === [] ? 'up_to_date' : 'migrated',
            'applied' => $completed,
            'ledger_count' => $total,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
    } finally {
        $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
    }
    return 0;
}

try {
    exit(migrateMain());
} catch (Throwable $exception) {
    fwrite(STDERR, '迁移失败：' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
