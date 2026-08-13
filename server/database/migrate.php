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
const PLUGIN_LIFECYCLE_MIGRATION = '20260814_plugin_module_lifecycle.sql';
const PLUGIN_LIFECYCLE_FAILED_CHECKSUM = 'fcd2375acc48d4b1ca58861fe5d3cb4e750062aa31a98e46e9b4563ebe08cfc4';
const PLUGIN_LIFECYCLE_FAILED_ERROR = "SQLSTATE[42S01]: Base table or view already exists: 1050 Table 'pa_permission' already exists";
const PLUGIN_LIFECYCLE_ADOPTED_PREFIX = [
    'pa_plugin_installation',
    'pa_plugin_module',
    'pa_module_migration',
];

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

/** @return array{email:string,password:string}|null */
function initialPlatformCredentialsForMigration(string $serverDir, string $adminEmail): ?array
{
    $fileConfig = migrationFileConfig($serverDir);
    $environmentMode = getenv('DEPLOYMENT_MODE');
    $mode = $environmentMode !== false && $environmentMode !== ''
        ? $environmentMode
        : ($fileConfig['DEPLOYMENT_MODE'] ?? '');
    if ($mode !== 'multi-tenant') {
        return null;
    }

    $environmentEmail = getenv('PLATFORM_INITIAL_EMAIL');
    $email = $environmentEmail !== false && $environmentEmail !== ''
        ? $environmentEmail
        : ($fileConfig['PLATFORM_INITIAL_EMAIL'] ?? '');
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new RuntimeException('PLATFORM_INITIAL_EMAIL 必须是有效邮箱');
    }
    $email = strtolower((string)$email);
    if (hash_equals($adminEmail, $email)) {
        throw new RuntimeException('PLATFORM_INITIAL_EMAIL 必须与 ADMIN_INITIAL_EMAIL 不同');
    }

    $environmentPassword = getenv('PLATFORM_INITIAL_PASSWORD');
    $password = $environmentPassword !== false && $environmentPassword !== ''
        ? $environmentPassword
        : ($fileConfig['PLATFORM_INITIAL_PASSWORD'] ?? '');
    if (strlen((string)$password) < 12
        || preg_match('/[A-Za-z]/', (string)$password) !== 1
        || preg_match('/\d/', (string)$password) !== 1) {
        throw new RuntimeException('PLATFORM_INITIAL_PASSWORD 至少 12 位且必须同时包含字母和数字');
    }

    return ['email' => $email, 'password' => (string)$password];
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

/** @return array<string, string> */
function migrationCreateTableStatements(string $file): array
{
    $sql = migrationSql($file);
    preg_match_all('/CREATE\s+TABLE\s+`([^`]+)`\s*\(.*?\n\)\s+ENGINE=.*?;/is', $sql, $matches, PREG_SET_ORDER);
    $statements = [];
    foreach ($matches as $match) {
        $statements[(string)$match[1]] = (string)$match[0];
    }
    if ($statements === [] || trim(implode("\n\n", array_values($statements))) !== trim($sql)) {
        throw new RuntimeException('迁移只允许包含可审计的 CREATE TABLE 语句：' . basename($file));
    }
    return $statements;
}

function migrationTableExistsByName(PDO $pdo, string $table): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?'
    );
    $statement->execute([$table]);
    return (int)$statement->fetchColumn() === 1;
}

function migrationStripOuterParentheses(string $expression): string
{
    $expression = trim($expression);
    while (str_starts_with($expression, '(') && str_ends_with($expression, ')')) {
        $depth = 0;
        $quote = false;
        $wraps = true;
        $length = strlen($expression);
        for ($index = 0; $index < $length; $index++) {
            $character = $expression[$index];
            if ($character === "'" && ($index === 0 || $expression[$index - 1] !== '\\')) {
                $quote = !$quote;
                continue;
            }
            if ($quote) {
                continue;
            }
            if ($character === '(') {
                $depth++;
            } elseif ($character === ')') {
                $depth--;
                if ($depth === 0 && $index !== $length - 1) {
                    $wraps = false;
                    break;
                }
            }
        }
        if (!$wraps || $depth !== 0) {
            break;
        }
        $expression = trim(substr($expression, 1, -1));
    }
    return $expression;
}

function migrationNormalizeCreateSql(string $sql): string
{
    $sql = strtolower(str_replace(['`', '_utf8mb4'], '', trim($sql, " \t\r\n;")));
    $sql = str_replace('default character set', 'default charset', $sql);
    $sql = preg_replace("/default\\s+'([0-9]+)'/", 'default $1', $sql) ?? $sql;
    $sql = preg_replace('/\\s+default\\s+null/', ' null', $sql) ?? $sql;
    $sql = preg_replace_callback('/check\\s*\\((.*)\\)(?=\\s*[,)]|\\s*engine)/is', static function (array $match): string {
        $expression = migrationStripOuterParentheses((string)$match[1]);
        do {
            $previous = $expression;
            $expression = preg_replace(
                "/\\(\\s*([a-z0-9_]+)\\s*(=|<>|is\\s+not\\s+null)\\s*('[^']*'|[a-z0-9_]+)?\\s*\\)/i",
                '$1 $2 $3',
                $expression
            ) ?? $expression;
        } while ($expression !== $previous);
        return 'check(' . migrationStripOuterParentheses($expression) . ')';
    }, $sql) ?? $sql;
    return preg_replace('/\\s+/', '', $sql) ?? $sql;
}

function migrationAssertExactTable(PDO $pdo, string $table, string $expectedSql, bool $mustBeEmpty): void
{
    if (!migrationTableExistsByName($pdo, $table)) {
        throw new RuntimeException("恢复要求的表不存在：{$table}");
    }
    $actual = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
    if (!is_array($actual) || !isset($actual[1])
        || !hash_equals(
            hash('sha256', migrationNormalizeCreateSql($expectedSql)),
            hash('sha256', migrationNormalizeCreateSql((string)$actual[1]))
        )) {
        throw new RuntimeException("恢复表结构与固定候选不一致：{$table}");
    }
    if ($mustBeEmpty && (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn() !== 0) {
        throw new RuntimeException("恢复表包含数据，拒绝采用：{$table}");
    }
}

function assertPluginLifecyclePermissionSchema(PDO $pdo): void
{
    if (!class_exists(\PeanutAdmin\Kernel\Persistence\Schema\KernelSchema::class)) {
        throw new RuntimeException('缺少 Core KernelSchema，无法核验 pa_permission');
    }
    migrationAssertExactTable(
        $pdo,
        'pa_permission',
        \PeanutAdmin\Kernel\Persistence\Schema\KernelSchema::createSql('pa_permission'),
        false
    );
}

function assertMigrationPrerequisites(PDO $pdo, string $name): void
{
    if ($name === PLUGIN_LIFECYCLE_MIGRATION) {
        assertPluginLifecyclePermissionSchema($pdo);
    }
}

function repairPluginLifecycleMigration(PDO $pdo, string $file, bool $checkOnly = false): array
{
    $rowStatement = $pdo->prepare(
        'SELECT migration,checksum,batch,status,started_at,applied_at,error '
        . 'FROM pa_schema_migration WHERE migration=? FOR UPDATE'
    );
    $rowStatement->execute([PLUGIN_LIFECYCLE_MIGRATION]);
    $row = $rowStatement->fetch();
    if (!is_array($row)
        || (string)$row['status'] !== 'failed'
        || !hash_equals(PLUGIN_LIFECYCLE_FAILED_CHECKSUM, (string)$row['checksum'])
        || !hash_equals(PLUGIN_LIFECYCLE_FAILED_ERROR, (string)$row['error'])
        || $row['applied_at'] !== null) {
        throw new RuntimeException('failed ledger 不符合已知 PR #116 恢复身份，拒绝修复');
    }

    $statements = migrationCreateTableStatements($file);
    foreach (PLUGIN_LIFECYCLE_ADOPTED_PREFIX as $table) {
        if (!isset($statements[$table])) {
            throw new RuntimeException("修正候选缺少恢复前缀：{$table}");
        }
        migrationAssertExactTable($pdo, $table, $statements[$table], true);
        unset($statements[$table]);
    }
    assertPluginLifecyclePermissionSchema($pdo);
    foreach (array_keys($statements) as $table) {
        if (migrationTableExistsByName($pdo, $table)) {
            throw new RuntimeException("恢复后缀表已存在，拒绝盲目续跑：{$table}");
        }
    }

    if ($checkOnly) {
        return [
            'migration' => PLUGIN_LIFECYCLE_MIGRATION,
            'previous_checksum' => PLUGIN_LIFECYCLE_FAILED_CHECKSUM,
            'checksum' => migrationChecksum($file),
            'adopted_empty_tables' => PLUGIN_LIFECYCLE_ADOPTED_PREFIX,
            'adopted_core_tables' => ['pa_permission'],
            'pending_tables' => array_keys($statements),
        ];
    }

    $newChecksum = migrationChecksum($file);
    $running = $pdo->prepare(
        "UPDATE pa_schema_migration SET checksum=?,status='running',started_at=?,applied_at=NULL,"
        . "error='PR116_SCHEMA_RECOVERY_VALIDATED' WHERE migration=? AND status='failed' AND checksum=? AND error=?"
    );
    $running->execute([
        $newChecksum,
        time(),
        PLUGIN_LIFECYCLE_MIGRATION,
        PLUGIN_LIFECYCLE_FAILED_CHECKSUM,
        PLUGIN_LIFECYCLE_FAILED_ERROR,
    ]);
    if ($running->rowCount() !== 1) {
        throw new RuntimeException('failed ledger 恢复声明发生竞争，拒绝继续');
    }

    try {
        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
        $finish = $pdo->prepare(
            "UPDATE pa_schema_migration SET status='applied',applied_at=?,error='' "
            . "WHERE migration=? AND status='running' AND checksum=?"
        );
        $finish->execute([time(), PLUGIN_LIFECYCLE_MIGRATION, $newChecksum]);
        if ($finish->rowCount() !== 1) {
            throw new RuntimeException('failed ledger 恢复完成写入发生竞争');
        }
    } catch (Throwable $exception) {
        $message = substr($exception->getMessage(), 0, 900);
        $failed = $pdo->prepare(
            "UPDATE pa_schema_migration SET status='failed',error=? WHERE migration=? AND checksum=?"
        );
        $failed->execute(['PR116_SCHEMA_RECOVERY_FAILED: ' . $message, PLUGIN_LIFECYCLE_MIGRATION, $newChecksum]);
        throw $exception;
    }
    return [
        'migration' => PLUGIN_LIFECYCLE_MIGRATION,
        'previous_checksum' => PLUGIN_LIFECYCLE_FAILED_CHECKSUM,
        'checksum' => $newChecksum,
        'adopted_empty_tables' => PLUGIN_LIFECYCLE_ADOPTED_PREFIX,
        'adopted_core_tables' => ['pa_permission'],
        'created_tables' => array_keys($statements),
    ];
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
            assertMigrationPrerequisites($pdo, $name);
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
        $repairArguments = array_values(array_filter(
            $_SERVER['argv'] ?? [],
            static fn(string $argument): bool => str_starts_with($argument, '--repair-failed=')
        ));
        if ($repairArguments !== []) {
            if ($repairArguments !== ['--repair-failed=' . PLUGIN_LIFECYCLE_MIGRATION]) {
                throw new RuntimeException('只支持固定 PR #116 lifecycle failed migration 恢复');
            }
            $file = $databaseDir . '/migrations/' . PLUGIN_LIFECYCLE_MIGRATION;
            $checkOnly = in_array('--check', $_SERVER['argv'] ?? [], true);
            $recovery = repairPluginLifecycleMigration($pdo, $file, $checkOnly);
            echo json_encode([
                'database' => $config['DB_NAME'],
                'status' => $checkOnly ? 'recovery_ready' : 'recovered',
                'recovery' => $recovery,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
            return 0;
        }
        $tenantBootstrap = null;
        $completed = applyPendingMigrations(
            $pdo,
            $files,
            static function (string $name) use ($pdo, &$tenantBootstrap): void {
                if ($name !== DefaultTenantBootstrap::MIGRATION) {
                    return;
                }
                $serverDir = dirname(__DIR__);
                $adminEmail = initialAdminEmailForMigration($serverDir);
                $adminPassword = initialAdminPasswordForMigration($serverDir);
                $platformCredentials = initialPlatformCredentialsForMigration($serverDir, $adminEmail);
                $tenantBootstrap = new DefaultTenantBootstrap($pdo);
                $tenantBootstrap->prepare(
                    $adminEmail,
                    $adminPassword,
                    $platformCredentials['email'] ?? null,
                    $platformCredentials['password'] ?? null
                );
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
