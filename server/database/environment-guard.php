<?php
declare(strict_types=1);

const PEANUT_DATABASE_RESOURCES = [
    'local-development' => [
        'peanut-admin-mysql84-development' => [
            'host' => '192.168.192.2',
            'port' => '20183',
            'database' => 'peanut_admin_development',
        ],
    ],
    'local-production-preview' => [
        'peanut-admin-mysql84-development' => [
            'host' => '192.168.192.2',
            'port' => '20183',
            'database' => 'peanut_admin_development',
        ],
    ],
    'production' => [
        'peanut-admin-production-bundled-mysql84' => [
            'host' => 'mysql',
            'port' => '3306',
            'database' => 'peanut_admin',
        ],
    ],
];

function requiredEnvironment(string $name): string
{
    $value = getenv($name);
    if ($value === false || trim($value) === '') {
        throw new RuntimeException("缺少环境配置：{$name}");
    }
    return trim($value);
}

/** @return array{environment:string,deployment_target:string,resource_id:string,host:string,port:string,database:string,user:string,password:string} */
function guardedDatabaseConfig(): array
{
    $environment = requiredEnvironment('APP_ENV');
    if (!in_array($environment, ['development', 'production'], true)) {
        throw new RuntimeException("不支持的 APP_ENV：{$environment}");
    }
    $deploymentTarget = requiredEnvironment('PEANUT_DEPLOYMENT_TARGET');
    $expectedAppEnvironment = $deploymentTarget === 'local-development' ? 'development' : 'production';
    if (!hash_equals($expectedAppEnvironment, $environment)) {
        throw new RuntimeException("APP_ENV={$environment} 与部署目标 {$deploymentTarget} 不匹配");
    }
    $resourceId = requiredEnvironment('PEANUT_DATABASE_RESOURCE_ID');
    $expected = PEANUT_DATABASE_RESOURCES[$deploymentTarget][$resourceId] ?? null;
    if (!is_array($expected)) {
        throw new RuntimeException("数据库资源 {$resourceId} 未登记为 {$deploymentTarget} 目标资源");
    }

    $actual = [
        'host' => requiredEnvironment('DB_HOST'),
        'port' => requiredEnvironment('DB_PORT'),
        'database' => requiredEnvironment('DB_NAME'),
    ];
    foreach ($expected as $name => $value) {
        if (!hash_equals($value, $actual[$name])) {
            throw new RuntimeException("数据库资源 {$resourceId} 的 {$name} 不匹配登记值");
        }
    }
    if (requiredEnvironment('DEPLOYMENT_MODE') !== 'standalone') {
        throw new RuntimeException('当前 Peanut Admin 运行环境必须显式使用 DEPLOYMENT_MODE=standalone');
    }

    return [
        'environment' => $environment,
        'deployment_target' => $deploymentTarget,
        'resource_id' => $resourceId,
        ...$actual,
        'user' => requiredEnvironment('DB_USER'),
        'password' => requiredEnvironment('DB_PASS'),
    ];
}

function guardedConnection(array $config): PDO
{
    return new PDO(
        sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $config['database']
        ),
        $config['user'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

function waitForDatabase(array $config, int $seconds): PDO
{
    $deadline = time() + $seconds;
    do {
        try {
            return guardedConnection($config);
        } catch (PDOException $exception) {
            if (time() >= $deadline) {
                throw new RuntimeException('数据库资源在等待窗口内不可连接', 0, $exception);
            }
            sleep(2);
        }
    } while (true);
}

/** @return array{migration_count:int,latest_migration:string,admin_count:int,menu_count:int,config_count:int,bootstrap_count:int} */
function assertCurrentDatabase(PDO $pdo): array
{
    $files = glob(__DIR__ . '/migrations/*.sql') ?: [];
    sort($files, SORT_STRING);
    $expected = [];
    foreach ($files as $file) {
        $checksum = hash_file('sha256', $file);
        if ($checksum === false) {
            throw new RuntimeException('无法计算迁移校验值：' . basename($file));
        }
        $expected[basename($file)] = $checksum;
    }

    $rows = $pdo->query(
        'SELECT migration, checksum, status FROM pa_schema_migration ORDER BY migration'
    )->fetchAll();
    $actual = [];
    foreach ($rows as $row) {
        $name = (string)$row['migration'];
        if (!isset($expected[$name])) {
            throw new RuntimeException('数据库存在代码中没有的迁移：' . $name);
        }
        if ((string)$row['status'] !== 'applied') {
            throw new RuntimeException('数据库存在未完成迁移：' . $name);
        }
        if (!hash_equals($expected[$name], (string)$row['checksum'])) {
            throw new RuntimeException('数据库迁移校验值与代码不一致：' . $name);
        }
        $actual[$name] = true;
    }
    $missing = array_values(array_diff(array_keys($expected), array_keys($actual)));
    if ($missing !== []) {
        throw new RuntimeException('数据库缺少迁移：' . implode(', ', $missing));
    }

    $adminCount = (int)$pdo->query(
        "SELECT COUNT(*) FROM pa_admin WHERE username = 'admin' AND root = 1 AND delete_time IS NULL"
    )->fetchColumn();
    $menuCount = (int)$pdo->query('SELECT COUNT(*) FROM pa_system_menu')->fetchColumn();
    $configCount = (int)$pdo->query('SELECT COUNT(*) FROM pa_config')->fetchColumn();
    $bootstrapCount = (int)$pdo->query(
        "SELECT COUNT(*) FROM pa_default_tenant_bootstrap WHERE status = 'completed'"
    )->fetchColumn();
    if ($adminCount !== 1 || $menuCount < 1 || $configCount < 1 || $bootstrapCount !== 1) {
        throw new RuntimeException('数据库基线数据不完整');
    }

    return [
        'migration_count' => count($actual),
        'latest_migration' => array_key_last($actual) ?? '',
        'admin_count' => $adminCount,
        'menu_count' => $menuCount,
        'config_count' => $configCount,
        'bootstrap_count' => $bootstrapCount,
    ];
}

try {
    $config = guardedDatabaseConfig();
    $wait = 0;
    foreach ($_SERVER['argv'] ?? [] as $argument) {
        if (preg_match('/^--wait=(\d+)$/D', (string)$argument, $matches) === 1) {
            $wait = min(300, max(0, (int)$matches[1]));
        }
    }
    $pdo = $wait > 0 ? waitForDatabase($config, $wait) : guardedConnection($config);
    $result = [
        'environment' => $config['environment'],
        'deployment_target' => $config['deployment_target'],
        'resource_id' => $config['resource_id'],
        'host' => $config['host'],
        'port' => (int)$config['port'],
        'database' => $config['database'],
        'status' => 'connected',
    ];
    if (in_array('--current', $_SERVER['argv'] ?? [], true)) {
        $result += assertCurrentDatabase($pdo);
        $result['status'] = 'current';
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, '数据库环境门禁失败：' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
