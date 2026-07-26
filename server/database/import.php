<?php
/**
 * 一次性种子导入脚本（临时）：用 .env 里的 DB 配置，通过 PDO 执行 init.sql。
 * init.sql 幂等（CREATE IF NOT EXISTS + INSERT IGNORE），可重复运行。
 * 用完即删。
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$env  = parse_ini_file($root . '/.env', true);
$db   = $env['DB_HOST'] ?? '127.0.0.1';
$get  = fn(string $k, string $d = '') => $env[$k] ?? getenv($k) ?: $d;

$host = $get('DB_HOST', '127.0.0.1');
$port = $get('DB_PORT', '3306');
$name = $get('DB_NAME', '');
$user = $get('DB_USER', 'root');
$pass = $get('DB_PASS', '');

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
