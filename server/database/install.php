<?php
declare(strict_types=1);

use app\common\service\tenant\DefaultTenantBootstrap;

/**
 * Peanut Admin fresh-database installer.
 *
 * Run from the repository root or server directory after creating an empty
 * database and configuring server/.env:
 *
 *     php server/database/install.php
 */

const REQUIRED_CONFIG = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS'];

function loadConfig(string $serverDir): array
{
    $fileConfig = loadFileConfig($serverDir);

    $config = [];
    foreach (REQUIRED_CONFIG as $name) {
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
function loadFileConfig(string $serverDir): array
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

function initialAdminPassword(string $serverDir): string
{
    $environment = getenv('ADMIN_INITIAL_PASSWORD');
    $password = $environment !== false && $environment !== ''
        ? $environment
        : (loadFileConfig($serverDir)['ADMIN_INITIAL_PASSWORD'] ?? '');
    validateInitialAdminPassword((string)$password);
    return (string)$password;
}

function initialAdminEmail(string $serverDir): string
{
    $environment = getenv('ADMIN_INITIAL_EMAIL');
    $email = $environment !== false && $environment !== ''
        ? $environment
        : (loadFileConfig($serverDir)['ADMIN_INITIAL_EMAIL'] ?? '');
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new RuntimeException('ADMIN_INITIAL_EMAIL 必须是有效邮箱');
    }
    return strtolower((string)$email);
}

function validateInitialAdminPassword(string $password): void
{
    if (strlen($password) < 12
        || preg_match('/[A-Za-z]/', $password) !== 1
        || preg_match('/\d/', $password) !== 1) {
        throw new RuntimeException('ADMIN_INITIAL_PASSWORD 至少 12 位且必须同时包含字母和数字');
    }
}

function replaceInitialAdminSeed(string $sql, string $password, ?string $salt = null): string
{
    validateInitialAdminPassword($password);
    $salt ??= bin2hex(random_bytes(8));
    if (preg_match('/^[a-f0-9]{16}$/', $salt) !== 1) {
        throw new RuntimeException('管理员初始盐格式错误');
    }

    $seed = "VALUES (1,'admin','超级管理员', MD5(CONCAT(MD5('admin123456'),'abcd1234')), 'abcd1234', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());";
    if (substr_count($sql, $seed) !== 1) {
        throw new RuntimeException('管理员 seed 与安装合同不一致');
    }
    $digest = md5(md5($password) . $salt);
    $replacement = "VALUES (1,'admin','超级管理员', '{$digest}', '{$salt}', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());";
    return str_replace($seed, $replacement, $sql);
}

/** @return array<string, string> */
function brandWebsiteDefaults(string $serverDir): array
{
    $path = $serverDir . '/config/brand.json';
    $json = file_get_contents($path);
    $manifest = is_string($json) ? json_decode($json, true) : null;
    if (!is_array($manifest)
        || ($manifest['schema_version'] ?? null) !== 1
        || !is_array($manifest['website'] ?? null)) {
        throw new RuntimeException('品牌默认配置格式错误');
    }
    $website = [];
    foreach ($manifest['website'] as $field => $value) {
        if (!is_string($field) || !is_string($value)) {
            throw new RuntimeException('品牌默认字段必须是字符串');
        }
        $website[$field] = $value;
    }
    return $website;
}

function sqlFiles(string $databaseDir): array
{
    $migrations = glob($databaseDir . '/migrations/*.sql') ?: [];
    sort($migrations, SORT_STRING);
    return array_merge([$databaseDir . '/init.sql'], $migrations);
}

function expectedTables(array $files): array
{
    $tables = [];
    foreach ($files as $file) {
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException('无法读取 SQL 文件：' . basename($file));
        }
        preg_match_all('/CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`([^`]+)`/i', $sql, $matches);
        foreach ($matches[1] as $table) {
            $tables[$table] = true;
        }
    }
    $names = array_keys($tables);
    sort($names, SORT_STRING);
    return $names;
}

function executeSqlFiles(PDO $pdo, array $files, string $adminPassword): void
{
    foreach ($files as $file) {
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException('无法读取 SQL 文件：' . basename($file));
        }
        if (basename($file) === 'init.sql') {
            $sql = replaceInitialAdminSeed($sql, $adminPassword);
        }
        try {
            $pdo->exec($sql);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                '执行 SQL 文件失败：' . basename($file) . '；' . $exception->getMessage(),
                0,
                $exception
            );
        }
    }
}

function executeSqlFile(PDO $pdo, string $file): void
{
    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException('无法读取 SQL 文件：' . basename($file));
    }
    try {
        $pdo->exec($sql);
    } catch (Throwable $exception) {
        throw new RuntimeException(
            '执行 SQL 文件失败：' . basename($file) . '；' . $exception->getMessage(),
            0,
            $exception
        );
    }
}

function defaultTenantBootstrap(PDO $pdo, string $serverDir, string $password): DefaultTenantBootstrap
{
    $autoload = $serverDir . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('缺少 Composer autoload，无法执行默认 Tenant bootstrap');
    }
    require_once $autoload;
    $bootstrap = new DefaultTenantBootstrap($pdo);
    $bootstrap->prepare(initialAdminEmail($serverDir), $password);
    return $bootstrap;
}

/** @param array<string, string> $website */
function seedBrandDefaults(PDO $pdo, array $website): void
{
    $statement = $pdo->prepare(
        'INSERT INTO pa_config (type, name, value, create_time, update_time) '
        . "VALUES ('website', ?, ?, ?, ?) "
        . 'ON DUPLICATE KEY UPDATE value = VALUES(value), update_time = VALUES(update_time)'
    );
    $now = time();
    $pdo->beginTransaction();
    try {
        foreach ($website as $field => $value) {
            $statement->execute([$field, $value, $now, $now]);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function recordInstalledMigrations(PDO $pdo, array $files): void
{
    $statement = $pdo->prepare(
        'INSERT INTO pa_schema_migration '
        . '(migration, checksum, batch, status, started_at, applied_at, error) '
        . "VALUES (?, ?, 1, 'applied', ?, ?, '')"
    );
    $now = time();
    foreach ($files as $file) {
        $checksum = hash_file('sha256', $file);
        if ($checksum === false) {
            throw new RuntimeException('无法计算迁移校验值：' . basename($file));
        }
        $statement->execute([basename($file), $checksum, $now, $now]);
    }
}

function main(): int
{
    $databaseDir = __DIR__;
    $serverDir = dirname($databaseDir);
    $config = loadConfig($serverDir);
    $database = $config['DB_NAME'];

    if (!preg_match('/^[A-Za-z0-9_]+$/', $database)) {
        throw new RuntimeException('DB_NAME 只能包含字母、数字和下划线');
    }

    $pdo = new PDO(
        sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $config['DB_HOST'],
            $config['DB_PORT'],
            $database
        ),
        $config['DB_USER'],
        $config['DB_PASS'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
        ]
    );

    $lockName = 'peanut_install_' . substr(hash('sha256', $database), 0, 48);
    $lockStatement = $pdo->prepare('SELECT GET_LOCK(?, 10)');
    $lockStatement->execute([$lockName]);
    if ((int)$lockStatement->fetchColumn() !== 1) {
        throw new RuntimeException('无法获取安装锁，请稍后重试');
    }

    try {
        $files = sqlFiles($databaseDir);
        $expected = expectedTables($files);
        $tableCountStatement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?'
        );
        $tableCountStatement->execute([$database]);
        if ((int)$tableCountStatement->fetchColumn() !== 0) {
            if (!in_array('--skip-if-installed', $_SERVER['argv'] ?? [], true)) {
                throw new RuntimeException('目标数据库不是空库，已拒绝执行首次安装');
            }

            $actualStatement = $pdo->prepare(
                'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME'
            );
            $actualStatement->execute([$database]);
            $actual = $actualStatement->fetchAll(PDO::FETCH_COLUMN);
            $missing = array_values(array_diff($expected, $actual));
            $defaultAdmin = in_array('pa_admin', $actual, true)
                ? (int)$pdo->query(
                    "SELECT COUNT(*) FROM pa_admin WHERE username = 'admin' AND root = 1"
                )->fetchColumn()
                : 0;
            $activeMenus = in_array('pa_system_menu', $actual, true)
                ? (int)$pdo->query('SELECT COUNT(*) FROM pa_system_menu')->fetchColumn()
                : 0;
            $configCount = in_array('pa_config', $actual, true)
                ? (int)$pdo->query('SELECT COUNT(*) FROM pa_config')->fetchColumn()
                : 0;
            if ($missing !== [] || $defaultAdmin !== 1 || $activeMenus === 0 || $configCount === 0) {
                throw new RuntimeException('已有数据库结构不完整，拒绝跳过安装：' . json_encode(
                    [
                        'missing_tables' => $missing,
                        'default_admin' => $defaultAdmin,
                        'active_menus' => $activeMenus,
                        'configs' => $configCount,
                    ],
                    JSON_UNESCAPED_UNICODE
                ));
            }

            echo json_encode([
                'database' => $database,
                'status' => 'already_installed',
                'tables' => count($actual),
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
            return 0;
        }

        $adminPassword = initialAdminPassword($serverDir);
        $brandDefaults = brandWebsiteDefaults($serverDir);
        $mt02Migration = '20260812-default-tenant-bootstrap.sql';
        $mt02File = $databaseDir . '/migrations/' . $mt02Migration;
        $initFile = array_shift($files);
        if (!is_string($initFile) || !is_file($mt02File)) {
            throw new RuntimeException('缺少 MT02 默认 Tenant migration');
        }
        $beforeMt02 = array_values(array_filter(
            $files,
            static fn(string $file): bool => basename($file) < $mt02Migration
        ));
        $afterMt02 = array_values(array_filter(
            $files,
            static fn(string $file): bool => basename($file) > $mt02Migration
        ));
        executeSqlFiles($pdo, [$initFile, ...$beforeMt02], $adminPassword);
        $tenantBootstrap = defaultTenantBootstrap($pdo, $serverDir, $adminPassword);
        executeSqlFile($pdo, $mt02File);
        $tenantBootstrap->complete();
        foreach ($afterMt02 as $file) {
            executeSqlFile($pdo, $file);
        }
        seedBrandDefaults($pdo, $brandDefaults);

        $actualStatement = $pdo->prepare(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME'
        );
        $actualStatement->execute([$database]);
        $actual = $actualStatement->fetchAll(PDO::FETCH_COLUMN);
        $missing = array_values(array_diff($expected, $actual));

        $defaultAdmin = (int)$pdo->query(
            "SELECT COUNT(*) FROM pa_admin WHERE username = 'admin' AND root = 1"
        )->fetchColumn();
        $activeMenus = (int)$pdo->query('SELECT COUNT(*) FROM pa_system_menu')->fetchColumn();
        $configCount = (int)$pdo->query('SELECT COUNT(*) FROM pa_config')->fetchColumn();

        if ($missing !== [] || $defaultAdmin !== 1 || $activeMenus === 0 || $configCount === 0) {
            throw new RuntimeException('安装结果不完整：' . json_encode([
                'missing_tables' => $missing,
                'default_admin' => $defaultAdmin,
                'active_menus' => $activeMenus,
                'configs' => $configCount,
            ], JSON_UNESCAPED_UNICODE));
        }

        recordInstalledMigrations($pdo, [...$beforeMt02, $mt02File, ...$afterMt02]);

        echo json_encode([
            'database' => $database,
            'sql_files' => count($files),
            'tables' => count($actual),
            'expected_tables' => count($expected),
            'default_admin' => $defaultAdmin,
            'admin_username' => 'admin',
            'active_menus' => $activeMenus,
            'configs' => $configCount,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
    } finally {
        $releaseStatement = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $releaseStatement->execute([$lockName]);
    }

    return 0;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        exit(main());
    } catch (Throwable $exception) {
        fwrite(STDERR, '安装失败：' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}
