<?php
declare(strict_types=1);

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

function executeSqlFiles(PDO $pdo, array $files): void
{
    foreach ($files as $file) {
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

        executeSqlFiles($pdo, $files);

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

        echo json_encode([
            'database' => $database,
            'sql_files' => count($files),
            'tables' => count($actual),
            'expected_tables' => count($expected),
            'default_admin' => $defaultAdmin,
            'active_menus' => $activeMenus,
            'configs' => $configCount,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
    } finally {
        $releaseStatement = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $releaseStatement->execute([$lockName]);
    }

    return 0;
}

try {
    exit(main());
} catch (Throwable $exception) {
    fwrite(STDERR, '安装失败：' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
