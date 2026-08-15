<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$script = $root . '/../scripts/seed-demo-data';
$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$expect(is_file($script), 'demo seed script is missing');
$planOutput = [];
$planExit = 0;
exec(escapeshellarg($script) . ' --plan 2>&1', $planOutput, $planExit);
$expect($planExit === 0, 'demo seed plan command failed: ' . implode("\n", $planOutput));
$plan = json_decode(implode("\n", $planOutput), true, 512, JSON_THROW_ON_ERROR);
$expect(($plan['status'] ?? null) === 'planned', 'demo seed plan status is invalid');
$expect(($plan['categories'] ?? 0) === 3, 'demo seed plan category count changed');
$expect(($plan['articles'] ?? 0) === 6, 'demo seed plan article count changed');
$expect(($plan['tags'] ?? 0) === 2, 'demo seed plan tag count changed');
$expect(($plan['members'] ?? 0) === 5, 'demo seed plan member count changed');

$applyOutput = [];
$applyExit = 0;
exec(escapeshellarg($script) . ' --apply 2>&1', $applyOutput, $applyExit);
$expect($applyExit !== 0, 'demo seed apply unexpectedly ran without explicit opt-in');
$expect(
    str_contains(implode("\n", $applyOutput), 'PEANUT_DEMO_MODE=enabled is required'),
    'demo seed apply did not fail at its explicit opt-in gate'
);

$source = (string) file_get_contents($script);
$expect(str_contains($source, "PEANUT_DEMO_MODE') !== 'enabled"), 'demo seed is missing its explicit opt-in gate');
$expect(str_contains($source, 'SELECT id FROM pa_article_cate WHERE tenant_id = :tenant_id AND name = :name LIMIT 1'), 'demo category seed must find existing rows without relying on a missing unique key');
$expect(str_contains($source, 'ON DUPLICATE KEY UPDATE'), 'demo member and tag seed must be idempotent');
$expect(str_contains($source, 'INSERT IGNORE INTO pa_member_tag_relation'), 'demo tag relations must be idempotent');
$expect(str_contains($source, 'guardedDatabaseConfig()'), 'demo seed must use the project environment guard');
$expect(str_contains($source, 'PEANUT_DEMO_TENANT_ID'), 'demo seed must require an explicit tenant');

echo "DEMO-DATA-SEED-001 contract passed\n";
