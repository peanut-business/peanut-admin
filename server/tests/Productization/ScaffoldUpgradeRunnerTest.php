<?php
declare(strict_types=1);

use app\common\service\scaffold\ScaffoldUpgradeRunner;

require dirname(__DIR__, 2) . '/app/common/service/scaffold/ScaffoldPathGuard.php';
require dirname(__DIR__, 2) . '/app/common/service/scaffold/ScaffoldManifest.php';
require dirname(__DIR__, 2) . '/app/common/service/scaffold/ScaffoldUpgradeLedger.php';
require dirname(__DIR__, 2) . '/app/common/service/scaffold/ScaffoldUpgradeRunner.php';

function scaffoldExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function scaffoldWrite(string $path, string $content): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0775, true)) {
        throw new RuntimeException("unable to create {$directory}");
    }
    if (file_put_contents($path, $content) === false) {
        throw new RuntimeException("unable to write {$path}");
    }
}

function scaffoldDelete(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            scaffoldDelete($path . DIRECTORY_SEPARATOR . $entry);
        }
        rmdir($path);
        return;
    }
    if (file_exists($path) || is_link($path)) {
        unlink($path);
    }
}

$fixture = dirname(__DIR__) . '/fixtures/scaffold-upgrade';
$project = sys_get_temp_dir() . '/peanut-scaffold-upgrade-' . bin2hex(random_bytes(6));
mkdir($project, 0775, true);

try {
    $base = [
        'managed.txt' => "managed base\n",
        'preserve.txt' => "preserve base\n",
        'merge.txt' => "merge base\n",
        'conflict.txt' => "conflict base\n",
        'deprecated.txt' => "deprecated base\n",
        'deleted.txt' => "deleted base\n",
        'renamed-old.txt' => "rename base\n",
    ];
    foreach ($base as $path => $content) {
        scaffoldWrite($project . '/' . $path, $content);
    }
    scaffoldWrite($project . '/preserve.txt', "user preserve\n");
    scaffoldWrite($project . '/merge.txt', "user merge\n");
    scaffoldWrite($project . '/conflict.txt', "user conflict\n");

    $runner = new ScaffoldUpgradeRunner();
    $plan = $runner->preflight(
        $project,
        $fixture . '/from/scaffold-manifest.json',
        $fixture . '/to/scaffold-manifest.json',
    );
    $actions = [];
    foreach ($plan['actions'] as $action) {
        $actions[$action['path']] = $action;
    }

    scaffoldExpect($plan['status'] === 'blocked', 'both-sides modification must block the candidate');
    scaffoldExpect($actions['managed.txt']['action'] === 'replace', 'pristine upstream change must plan replacement');
    scaffoldExpect($actions['preserve.txt']['action'] === 'preserve', 'project-only preserve file must remain');
    scaffoldExpect($actions['merge.txt']['action'] === 'merge', 'project-only merge file must enter merge queue');
    scaffoldExpect($actions['conflict.txt']['reason'] === 'both_project_and_upstream_modified', 'both changes must report conflict');
    scaffoldExpect($actions['deprecated.txt']['action'] === 'deprecated', 'deprecated file must report migration');
    scaffoldExpect($actions['deleted.txt']['action'] === 'deprecated', 'removed file must be explicitly reported');
    scaffoldExpect($actions['renamed-new.txt']['action'] === 'rename', 'explicit pristine rename must be planned');
    scaffoldExpect(file_get_contents($project . '/managed.txt') === "managed base\n", 'dry-run must not overwrite files');
    scaffoldExpect(is_file($project . '/' . $plan['backup']['path']), 'recovery inventory must exist before apply');

    $ledgerPath = $project . '/.peanut/upgrades/ledger.ndjson';
    $ledgerBefore = file_get_contents($ledgerPath);
    $planAgain = $runner->preflight(
        $project,
        $fixture . '/from/scaffold-manifest.json',
        $fixture . '/to/scaffold-manifest.json',
    );
    scaffoldExpect($planAgain['candidate'] === $plan['candidate'], 'idempotent dry-run must retain candidate identity');
    scaffoldExpect(file_get_contents($ledgerPath) === $ledgerBefore, 'idempotent dry-run must not duplicate the ledger');

    $invalid = json_decode((string)file_get_contents($fixture . '/to/scaffold-manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    $invalid['files'][0]['path'] = '../outside.txt';
    $invalidManifest = $project . '/invalid-manifest.json';
    scaffoldWrite($invalidManifest, json_encode($invalid, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    try {
        $runner->preflight($project, $fixture . '/from/scaffold-manifest.json', $invalidManifest);
        throw new RuntimeException('path traversal was accepted');
    } catch (RuntimeException $exception) {
        scaffoldExpect(str_starts_with($exception->getMessage(), 'SCAFFOLD_PATH_OUTSIDE_PROJECT:'), 'path traversal must fail closed');
    }

    $outside = $project . '-outside.txt';
    scaffoldWrite($outside, "outside\n");
    unlink($project . '/managed.txt');
    symlink($outside, $project . '/managed.txt');
    try {
        $runner->preflight($project, $fixture . '/from/scaffold-manifest.json', $fixture . '/to/scaffold-manifest.json');
        throw new RuntimeException('symlink path was accepted');
    } catch (RuntimeException $exception) {
        scaffoldExpect(str_starts_with($exception->getMessage(), 'SCAFFOLD_PATH_SYMLINK_REJECTED:'), 'symlink path must fail closed');
    }
    unlink($outside);
} finally {
    scaffoldDelete($project);
}

echo "SCAFFOLD-UPGRADE-RUNNER-001 passed\n";
