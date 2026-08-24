<?php
/**
 * 一次性种子导入脚本（临时）：用已登记的进程 DB 配置，通过 PDO 执行 init.sql。
 * init.sql 幂等（CREATE IF NOT EXISTS + INSERT IGNORE），可重复运行。
 * 用完即删。
 */
declare(strict_types=1);

require_once __DIR__ . '/environment-guard.php';

$hostLeaseProof = getenv('P0E_HOST_LEASE_PROOF');
$config = guardedDatabaseConfig(
    $hostLeaseProof === false || trim($hostLeaseProof) === '' ? null : $hostLeaseProof
);
$host = $config['host'];
$port = $config['port'];
$name = $config['database'];
$user = $config['user'];
$pass = $config['password'];

$dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
echo "连接 $dsn (user=$user)\n";

$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$sql = file_get_contents(__DIR__ . '/init.sql');
// 先逐行剔除 -- 注释行，再按分号拆分（种子里无存储过程/触发器）
$sql = implode("\n", array_filter(
    explode("\n", $sql),
    fn($line) => !str_starts_with(ltrim($line), '--')
));
$stmts = array_filter(array_map('trim', explode(';', $sql)), fn($s) => $s !== '');

$ok = 0;
foreach ($stmts as $stmt) {
    if (trim($stmt) === '') continue;
    $pdo->exec($stmt);
    $ok++;
}
echo "执行完成，语句数：$ok\n";

// 汇总
foreach (['pa_admin', 'pa_system_menu', 'pa_system_role', 'pa_system_role_menu', 'pa_admin_role'] as $t) {
    $n = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    echo sprintf("  %-22s %s 行\n", $t, $n);
}
