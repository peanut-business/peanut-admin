<?php
declare(strict_types=1);

use app\common\service\scaffold\ApplicationCreator;

$root = dirname(__DIR__, 3);
require $root . '/server/app/common/service/scaffold/ScaffoldPathGuard.php';
require $root . '/server/app/common/service/scaffold/ScaffoldManifest.php';
require $root . '/server/app/common/service/scaffold/ApplicationCreator.php';

function createApplicationExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function createApplicationDelete(string $path): void
{
    if (!file_exists($path) && !is_link($path)) return;
    if (is_dir($path) && !is_link($path)) {
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            createApplicationDelete($path . '/' . $entry);
        }
        rmdir($path);
        return;
    }
    unlink($path);
}

function createApplicationFails(callable $operation, string $prefix): void
{
    try {
        $operation();
        throw new RuntimeException("expected {$prefix}");
    } catch (RuntimeException $exception) {
        createApplicationExpect(str_starts_with($exception->getMessage(), $prefix), "unexpected error: {$exception->getMessage()}");
    }
}

/** @return list<string> */
function createApplicationFiles(string $root): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile()) $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    }
    sort($files, SORT_STRING);
    return $files;
}

$systemTemporary = realpath(sys_get_temp_dir());
createApplicationExpect(is_string($systemTemporary), 'system temporary directory must resolve');
$temporary = $systemTemporary . '/peanut-create-app-' . bin2hex(random_bytes(6));
mkdir($temporary, 0775, true);
$inventoryPath = $root . '/scaffold/application-template-inventory.json';
$identity = ['commit' => str_repeat('a', 40), 'tree' => str_repeat('b', 40)];

try {
    $creator = new ApplicationCreator($root, $inventoryPath, $identity);
    $first = $temporary . '/first';
    $second = $temporary . '/second';
    $manifestOne = $creator->create('Acme Console', 'acme-console', 'acme/acme-console', $first);
    $manifestTwo = $creator->create('Acme Console', 'acme-console', 'acme/acme-console', $second);

    createApplicationExpect($manifestOne === $manifestTwo, 'same template identity and parameters must produce the same manifest');
    createApplicationExpect(
        hash_file('sha256', $first . '/.peanut/application-manifest.json') === hash_file('sha256', $second . '/.peanut/application-manifest.json'),
        'application manifests must be byte-identical'
    );
    foreach ($manifestOne['files'] as $file) {
        createApplicationExpect(
            hash_file('sha256', $first . '/' . $file['path']) === hash_file('sha256', $second . '/' . $file['path']),
            'generated file changed across identical runs: ' . $file['path']
        );
    }

    $inventory = json_decode((string)file_get_contents($inventoryPath), true, 512, JSON_THROW_ON_ERROR);
    $expected = ['.peanut/application-manifest.json'];
    foreach ($inventory['files'] as $entry) {
        if ($entry['classification'] === 'excluded') continue;
        $expected[] = $entry['target'];
        if (in_array($entry['classification'], ['managed', 'generated-managed'], true)) {
            $expected[] = '.peanut/scaffold-baseline/' . $inventory['template_version'] . '/files/' . $entry['target'];
        }
    }
    sort($expected, SORT_STRING);
    createApplicationExpect(createApplicationFiles($first) === $expected, 'generated tree must exactly match inventory plus declared metadata/baselines');
    createApplicationExpect(!is_dir($first . '/.git') && !is_dir($first . '/output'), 'generated application must exclude Git and historical output');
    createApplicationExpect(!is_file($first . '/AGENTS.md'), 'source governance evidence must be excluded');
    $releaseMetadata = json_decode((string)file_get_contents($first . '/RELEASE_METADATA.json'), true, 512, JSON_THROW_ON_ERROR);
    createApplicationExpect($releaseMetadata['product'] === 'Acme Console' && $releaseMetadata['version'] === '0.1.0', 'release metadata must be regenerated for the new application');
    createApplicationExpect(!str_contains((string)file_get_contents($first . '/server/database/init.sql'), "MD5(CONCAT(MD5('admin123456')"), 'shared default password must be absent');
    createApplicationExpect((string)json_decode((string)file_get_contents($first . '/server/config/brand.json'), true)['website']['name'] === 'Acme Console', 'generated brand identity must be used');
    createApplicationExpect(is_file($first . '/server/config/peanut.php') && is_file($first . '/web/src/peanut.overrides.ts'), 'stable Host override entries must be preserved');
    foreach (createApplicationFiles($first) as $path) {
        $absolute = $first . '/' . $path;
        if (filesize($absolute) > 5_000_000) continue;
        $content = file_get_contents($absolute);
        if (!is_string($content)) continue;
        createApplicationExpect(!str_contains($content, 'Peanut Admin'), 'source application brand leaked into ' . $path);
        createApplicationExpect(!str_contains($content, '花生科技'), 'source company brand leaked into ' . $path);
        createApplicationExpect(!str_contains($content, '/Users/xing'), 'personal path leaked into ' . $path);
        createApplicationExpect(!str_contains($content, '192.168.192.2'), 'source infrastructure leaked into ' . $path);
        createApplicationExpect(!str_contains($content, 'peanut-admin.007345.xyz'), 'source production domain leaked into ' . $path);
    }

    mkdir($temporary . '/non-empty');
    file_put_contents($temporary . '/non-empty/keep.txt', 'keep');
    createApplicationFails(fn() => $creator->create('Acme Console', 'acme-console', 'acme/acme-console', $temporary . '/non-empty'), 'CREATE_APP_TARGET_NOT_EMPTY');
    createApplicationFails(fn() => $creator->create('Acme Console', '../bad', 'acme/acme-console', $temporary . '/bad-slug'), 'CREATE_APP_SLUG_INVALID');
    createApplicationFails(fn() => $creator->create('Acme Console', 'acme-console', 'acme/acme-console', $temporary . '/../escape'), 'CREATE_APP_TARGET_PATH_INVALID');

    mkdir($temporary . '/outside');
    symlink($temporary . '/outside', $temporary . '/linked-target');
    createApplicationFails(fn() => $creator->create('Acme Console', 'acme-console', 'acme/acme-console', $temporary . '/linked-target'), 'CREATE_APP_TARGET_SYMLINK_REJECTED');
    mkdir($temporary . '/outside-parent');
    symlink($temporary . '/outside-parent', $temporary . '/linked-parent');
    createApplicationFails(fn() => $creator->create('Acme Console', 'acme-console', 'acme/acme-console', $temporary . '/linked-parent/escape'), 'CREATE_APP_TARGET_SYMLINK_REJECTED');

    $generatedCi = (string)file_get_contents($first . '/.github/workflows/ci.yml');
    createApplicationExpect(!str_contains($generatedCi, 'stale-facts:') && !str_contains($generatedCi, 'create-app:'), 'generated CI must not depend on source-template governance jobs');
    createApplicationExpect(is_file($first . '/server/database/environment-guard.php'), 'production database guard must remain in the deployment inventory');

    $unknown = $inventory;
    foreach ($unknown['files'] as &$entry) {
        if ($entry['classification'] !== 'excluded') {
            $entry['transform'] = 'unknown-transform';
            break;
        }
    }
    unset($entry);
    $unknownPath = $temporary . '/unknown.json';
    file_put_contents($unknownPath, json_encode($unknown, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $unknownCreator = new ApplicationCreator($root, $unknownPath, $identity);
    createApplicationFails(fn() => $unknownCreator->create('Acme Console', 'acme-console', 'acme/acme-console', $temporary . '/unknown'), 'CREATE_APP_INVENTORY_ENTRY_INVALID');

    $unknownVariable = $inventory;
    $unknownVariable['variables'][] = 'UNDECLARED_INPUT';
    $unknownVariablePath = $temporary . '/unknown-variable.json';
    file_put_contents($unknownVariablePath, json_encode($unknownVariable, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $unknownVariableCreator = new ApplicationCreator($root, $unknownVariablePath, $identity);
    createApplicationFails(fn() => $unknownVariableCreator->create('Acme Console', 'acme-console', 'acme/acme-console', $temporary . '/unknown-variable'), 'CREATE_APP_INVENTORY_UNKNOWN_VARIABLE');
} finally {
    createApplicationDelete($temporary);
}

echo "CREATE-APP-001 passed\n";
