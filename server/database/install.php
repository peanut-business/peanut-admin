<?php
declare(strict_types=1);

use PeanutAdmin\Kernel\Identity\PasswordHasher;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoAuditRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoIdentityRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoMembershipRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoPlatformRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTenantRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTransactionManager;
use PeanutAdmin\Kernel\Persistence\Schema\KernelSchema;
use PeanutAdmin\Kernel\Platform\Bootstrap\BootstrapService;

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
    if (getenv('PEANUT_DEMO_MODE') === 'enabled') {
        if ($password !== 'peanut1234') {
            throw new RuntimeException('演示模式的初始管理员密码必须统一为 peanut1234');
        }
        return;
    }
    if (strlen($password) < 6) {
        throw new RuntimeException('ADMIN_INITIAL_PASSWORD 至少 6 位');
    }
}

/** @return array{email:string,password:string}|null */
function initialPlatformCredentials(string $serverDir, string $adminEmail): ?array
{
    $fileConfig = loadFileConfig($serverDir);
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
    if (getenv('PEANUT_DEMO_MODE') === 'enabled') {
        if ((string)$password !== 'peanut1234') {
            throw new RuntimeException('演示模式的 Platform 初始密码必须统一为 peanut1234');
        }
        return ['email' => $email, 'password' => (string)$password];
    }
    if (strlen((string)$password) < 6) {
        throw new RuntimeException('PLATFORM_INITIAL_PASSWORD 至少 6 位');
    }

    return ['email' => $email, 'password' => (string)$password];
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
    return [$databaseDir . '/init.sql'];
}

function loadCoreRuntime(string $serverDir): void
{
    $autoload = $serverDir . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('缺少 Composer autoload，无法创建 Core Schema');
    }
    require_once $autoload;
}

function expectedTables(array $files): array
{
    $tables = array_fill_keys(KernelSchema::tableNames(), true);
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

/**
 * @param array{email:string,password:string}|null $platformCredentials
 * @return array{tenant_id:int,account_id:int,member_id:int,operator_id:int}
 */
function initializeCoreIdentity(
    PDO $pdo,
    string $email,
    string $password,
    ?array $platformCredentials
): array
{
    foreach (KernelSchema::tableNames() as $table) {
        $pdo->exec(KernelSchema::createSql($table));
    }
    ensureTenantChallengeClientKey($pdo);
    $pdo->exec(KernelSchema::addTenantMemberDepartmentForeignKeySql());

    $service = new BootstrapService(
        new PdoTransactionManager($pdo),
        new PdoIdentityRepository($pdo),
        new PdoTenantRepository($pdo),
        new PdoMembershipRepository($pdo),
        new PdoPlatformRepository($pdo),
        new PdoAuditRepository($pdo),
        new PasswordHasher()
    );
    $separatePlatformOperator = $platformCredentials !== null;
    $demoBootstrapPassword = \app\common\service\DemoAccountPolicy::enabled()
        ? \app\common\service\DemoAccountPolicy::bootstrapPassword()
        : null;
    $platformPassword = $demoBootstrapPassword
        ?? ($platformCredentials['password'] ?? $password);
    $ownerPassword = $separatePlatformOperator
        ? ($demoBootstrapPassword ?? $password)
        : null;
    $platform = $service->bootstrapPlatformOwner(
        $platformCredentials['email'] ?? $email,
        $platformPassword,
        $separatePlatformOperator ? 'Platform Operator' : '超级管理员',
        'fresh-install-platform-owner'
    );
    $owner = $service->provisionTenantOwnerCandidate(
        $platform->operatorId,
        'default',
        'Peanut Admin',
        $email,
        $ownerPassword,
        '超级管理员',
        'fresh-install-default-owner'
    );
    $service->activateTenantOwner(
        $platform->operatorId,
        $owner->tenantId,
        $owner->memberId,
        'fresh-install-default-owner-activate'
    );
    $service->activateTenant(
        $platform->operatorId,
        $owner->tenantId,
        'fresh-install-default-tenant-activate'
    );

    return [
        'tenant_id' => $owner->tenantId,
        'account_id' => $owner->accountId,
        'member_id' => $owner->memberId,
        'operator_id' => $platform->operatorId,
    ];
}

/**
 * The alpha.5 Core package persists the client binding but its exported
 * fresh schema predates that column. Keep a fresh install compatible with the
 * repository contract until the package's canonical KernelSchema is released.
 */
function ensureTenantChallengeClientKey(PDO $pdo): void
{
    $column = $pdo->query(
        "SHOW COLUMNS FROM `pa_login_challenge` LIKE 'client_key'"
    )->fetch(PDO::FETCH_ASSOC);
    if ($column !== false) {
        return;
    }

    $pdo->exec(<<<'SQL'
ALTER TABLE `pa_login_challenge`
  ADD COLUMN `client_key` VARCHAR(64) NOT NULL AFTER `purpose`,
  ADD CONSTRAINT `chk_login_challenge_client`
    CHECK (REGEXP_LIKE(`client_key`, '^[a-z][a-z0-9-]{0,63}$', 'c'))
SQL);
}

/** @return array{tenant_count:int,owner_count:int,operator_count:int} */
function coreIdentityCounts(PDO $pdo): array
{
    return [
        'tenant_count' => (int)$pdo->query(
            "SELECT COUNT(*) FROM pa_tenant WHERE code = 'default' AND status = 'active'"
        )->fetchColumn(),
        'owner_count' => (int)$pdo->query(<<<'SQL'
SELECT COUNT(DISTINCT tm.id)
FROM pa_tenant t
JOIN pa_tenant_member tm ON tm.tenant_id = t.id AND tm.status = 'active'
JOIN pa_account a ON a.id = tm.account_id AND a.status = 'active'
JOIN pa_credential c ON c.account_id = a.id AND c.status = 'active'
JOIN pa_member_role mr ON mr.tenant_id = tm.tenant_id AND mr.tenant_member_id = tm.id
JOIN pa_role r ON r.tenant_id = mr.tenant_id AND r.id = mr.role_id
WHERE t.code = 'default' AND t.status = 'active' AND r.`key` = 'core.tenant-owner'
SQL)->fetchColumn(),
        'operator_count' => (int)$pdo->query(
            "SELECT COUNT(*) FROM pa_platform_operator WHERE status = 'active'"
        )->fetchColumn(),
    ];
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

function main(): int
{
    $databaseDir = __DIR__;
    $serverDir = dirname($databaseDir);
    loadCoreRuntime($serverDir);
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
            $activeMenus = in_array('pa_system_menu', $actual, true)
                ? (int)$pdo->query('SELECT COUNT(*) FROM pa_system_menu')->fetchColumn()
                : 0;
            $configCount = in_array('pa_config', $actual, true)
                ? (int)$pdo->query('SELECT COUNT(*) FROM pa_config')->fetchColumn()
                : 0;
            $coreIdentity = $missing === []
                ? coreIdentityCounts($pdo)
                : ['tenant_count' => 0, 'owner_count' => 0, 'operator_count' => 0];
            if ($missing !== []
                || $activeMenus === 0
                || $configCount === 0
                || $coreIdentity !== ['tenant_count' => 1, 'owner_count' => 1, 'operator_count' => 1]) {
                throw new RuntimeException('已有数据库结构不完整，拒绝跳过安装：' . json_encode(
                    [
                        'missing_tables' => $missing,
                        'active_menus' => $activeMenus,
                        'configs' => $configCount,
                        'core_identity' => $coreIdentity,
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
        $adminEmail = initialAdminEmail($serverDir);
        $platformCredentials = initialPlatformCredentials($serverDir, $adminEmail);
        $brandDefaults = brandWebsiteDefaults($serverDir);
        $coreIdentity = initializeCoreIdentity(
            $pdo,
            $adminEmail,
            $adminPassword,
            $platformCredentials
        );
        if (\app\common\service\DemoAccountPolicy::enabled()) {
            \app\common\service\DemoAccountPolicy::replaceCredentialHashes(
                $pdo,
                array_values(array_filter([
                    $adminEmail,
                    $platformCredentials['email'] ?? '',
                ])),
            );
        }
        executeSqlFiles($pdo, $files);
        seedBrandDefaults($pdo, $brandDefaults);

        $actualStatement = $pdo->prepare(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME'
        );
        $actualStatement->execute([$database]);
        $actual = $actualStatement->fetchAll(PDO::FETCH_COLUMN);
        $missing = array_values(array_diff($expected, $actual));

        $activeMenus = (int)$pdo->query('SELECT COUNT(*) FROM pa_system_menu')->fetchColumn();
        $configCount = (int)$pdo->query('SELECT COUNT(*) FROM pa_config')->fetchColumn();
        $coreIdentityCounts = coreIdentityCounts($pdo);

        if ($missing !== []
            || $activeMenus === 0
            || $configCount === 0
            || $coreIdentityCounts !== ['tenant_count' => 1, 'owner_count' => 1, 'operator_count' => 1]) {
            throw new RuntimeException('安装结果不完整：' . json_encode([
                'missing_tables' => $missing,
                'active_menus' => $activeMenus,
                'configs' => $configCount,
                'core_identity' => $coreIdentityCounts,
            ], JSON_UNESCAPED_UNICODE));
        }

        echo json_encode([
            'database' => $database,
            'baseline' => 'init.sql',
            'tables' => count($actual),
            'expected_tables' => count($expected),
            'active_menus' => $activeMenus,
            'configs' => $configCount,
            'default_tenant_id' => $coreIdentity['tenant_id'],
            'owner_account_id' => $coreIdentity['account_id'],
            'owner_member_id' => $coreIdentity['member_id'],
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
