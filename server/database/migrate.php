<?php
declare(strict_types=1);

require_once __DIR__ . '/install.php';

/** @return list<string> */
function activeMigrationFiles(string $databaseDir): array
{
    $files = glob($databaseDir . '/migrations/*.sql') ?: [];
    sort($files, SORT_STRING);
    foreach ($files as $file) {
        if (preg_match('/^\d{8}[-_][a-z0-9][a-z0-9_-]*\.sql$/D', basename($file)) !== 1) {
            throw new RuntimeException('追加迁移文件名不符合约定：' . basename($file));
        }
    }
    return $files;
}

function assertAdditiveMigration(string $file): void
{
    $sql = file_get_contents($file);
    if (!is_string($sql) || trim($sql) === '') {
        throw new RuntimeException('追加迁移为空或不可读：' . basename($file));
    }
    if (preg_match(
        '/\b(?:UPDATE|DELETE\s+FROM|DROP\s+(?:TABLE|COLUMN|INDEX)|RENAME\s+TABLE|TRUNCATE|PREPARE|EXECUTE)\b'
        . '|\bALTER\s+TABLE\b[\s\S]*?\b(?:MODIFY|CHANGE)\s+(?:COLUMN\s+)?'
        . '|\binformation_schema\b|\b(?:backfill|adopt|compatib(?:ility|le)?|legacy)\b/i',
        $sql
    ) === 1) {
        throw new RuntimeException('追加迁移包含 fresh baseline 禁止的历史或破坏性操作：' . basename($file));
    }
}

/** @return array<string,string> */
function expectedDatabaseIdentities(string $databaseDir): array
{
    $files = [$databaseDir . '/init.sql', ...activeMigrationFiles($databaseDir)];
    $expected = [];
    foreach ($files as $file) {
        $checksum = hash_file('sha256', $file);
        if ($checksum === false) {
            throw new RuntimeException('无法计算数据库文件校验值：' . basename($file));
        }
        $expected[basename($file)] = $checksum;
    }
    return $expected;
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

/**
 * @param array<string,string> $expected
 * @return array<string,array{checksum:string,status:string}>
 */
function assertFreshBaselineLedger(PDO $pdo, array $expected): array
{
    $tableExists = (int)$pdo->query(<<<'SQL'
SELECT COUNT(*) FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_schema_migration'
SQL)->fetchColumn();
    if ($tableExists !== 1) {
        throw new RuntimeException('数据库没有 canonical fresh baseline；请对空库执行 install.php');
    }

    $rows = $pdo->query(
        'SELECT migration, checksum, status FROM pa_schema_migration ORDER BY migration'
    )->fetchAll();
    $actual = [];
    foreach ($rows as $row) {
        $name = (string)$row['migration'];
        if (!isset($expected[$name])) {
            throw new RuntimeException('数据库账本不属于当前 fresh baseline：' . $name);
        }
        $checksum = (string)$row['checksum'];
        $status = (string)$row['status'];
        if (!hash_equals($expected[$name], $checksum)) {
            throw new RuntimeException('数据库文件校验值不一致：' . $name);
        }
        if ($status !== 'applied') {
            throw new RuntimeException('数据库存在未完成的追加迁移：' . $name);
        }
        $actual[$name] = ['checksum' => $checksum, 'status' => $status];
    }

    if (!isset($actual['init.sql'])) {
        throw new RuntimeException('数据库缺少 canonical init.sql 基线身份；不支持旧版本原地升级');
    }
    return $actual;
}

function applyAdditiveMigration(PDO $pdo, string $file, string $checksum, int $batch): void
{
    assertAdditiveMigration($file);
    $name = basename($file);
    $startedAt = time();
    $insert = $pdo->prepare(
        'INSERT INTO pa_schema_migration '
        . '(migration, checksum, batch, status, started_at, applied_at, error) '
        . "VALUES (?, ?, ?, 'applying', ?, NULL, '')"
    );
    $insert->execute([$name, $checksum, $batch, $startedAt]);

    try {
        $sql = file_get_contents($file);
        if (!is_string($sql)) {
            throw new RuntimeException('无法读取追加迁移：' . $name);
        }
        $pdo->exec($sql);
        $complete = $pdo->prepare(
            "UPDATE pa_schema_migration SET status='applied', applied_at=?, error='' WHERE migration=?"
        );
        $complete->execute([time(), $name]);
    } catch (Throwable $exception) {
        $failed = $pdo->prepare(
            "UPDATE pa_schema_migration SET status='failed', error=? WHERE migration=?"
        );
        $failed->execute([substr($exception->getMessage(), 0, 1000), $name]);
        throw new RuntimeException('追加迁移失败：' . $name . '；' . $exception->getMessage(), 0, $exception);
    }
}

/** @param list<string> $arguments */
function migrationMain(array $arguments): int
{
    $allowed = ['--current'];
    foreach (array_slice($arguments, 1) as $argument) {
        if (!in_array($argument, $allowed, true)) {
            throw new RuntimeException('未知迁移参数：' . $argument);
        }
    }

    $databaseDir = __DIR__;
    $serverDir = dirname($databaseDir);
    $config = loadConfig($serverDir);
    $pdo = migrationConnection($config);
    $lockName = 'peanut_migrate_' . substr(hash('sha256', $config['DB_NAME']), 0, 48);
    $lock = $pdo->prepare('SELECT GET_LOCK(?, 10)');
    $lock->execute([$lockName]);
    if ((int)$lock->fetchColumn() !== 1) {
        throw new RuntimeException('无法获取迁移锁，请稍后重试');
    }

    try {
        $expected = expectedDatabaseIdentities($databaseDir);
        $actual = assertFreshBaselineLedger($pdo, $expected);
        $missing = array_values(array_diff(array_keys($expected), array_keys($actual)));
        $missing = array_values(array_filter($missing, static fn(string $name): bool => $name !== 'init.sql'));
        if (in_array('--current', $arguments, true) && $missing !== []) {
            throw new RuntimeException('数据库缺少追加迁移：' . implode(', ', $missing));
        }

        $batch = (int)$pdo->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM pa_schema_migration')->fetchColumn();
        foreach ($missing as $name) {
            applyAdditiveMigration(
                $pdo,
                $databaseDir . '/migrations/' . $name,
                $expected[$name],
                $batch
            );
        }

        echo json_encode([
            'database' => $config['DB_NAME'],
            'baseline' => 'init.sql',
            'baseline_checksum' => $expected['init.sql'],
            'applied' => $missing,
            'migration_count' => count($expected),
            'status' => 'current',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
    } finally {
        $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
    }

    return 0;
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    try {
        exit(migrationMain($_SERVER['argv'] ?? []));
    } catch (Throwable $exception) {
        fwrite(STDERR, '迁移失败：' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}
