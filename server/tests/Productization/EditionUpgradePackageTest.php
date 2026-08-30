<?php
declare(strict_types=1);

use app\common\service\scaffold\EditionUpgradePackage;
use app\common\service\scaffold\ScaffoldUpgradeRunner;

$root = dirname(__DIR__, 3);
require_once $root . '/scripts/scaffold-runtime/ScaffoldPathGuard.php';
require_once $root . '/scripts/scaffold-runtime/ScaffoldManifest.php';
require_once $root . '/scripts/scaffold-runtime/ScaffoldUpgradeLedger.php';
require_once $root . '/scripts/scaffold-runtime/ScaffoldUpgradeRunner.php';
require_once $root . '/scripts/scaffold-runtime/EditionUpgradePackage.php';

function editionUpgradeExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function editionUpgradeRemove(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) editionUpgradeRemove($path . '/' . $entry);
        rmdir($path);
        return;
    }
    if (file_exists($path) || is_link($path)) unlink($path);
}

function editionUpgradeJson(string $path, array $data): void
{
    if (!is_dir(dirname($path))) mkdir(dirname($path), 0775, true);
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
}

function editionUpgradeFile(string $path, string $contents, int $mode = 0644): void
{
    if (!is_dir(dirname($path))) mkdir(dirname($path), 0775, true);
    file_put_contents($path, $contents);
    chmod($path, $mode);
}

function editionUpgradeFails(callable $operation, string $error): void
{
    try {
        $operation();
        throw new RuntimeException('expected ' . $error);
    } catch (RuntimeException $exception) {
        editionUpgradeExpect(str_starts_with($exception->getMessage(), $error), 'unexpected error: ' . $exception->getMessage());
    }
}

$temporaryRoot = realpath(sys_get_temp_dir());
editionUpgradeExpect(is_string($temporaryRoot), 'temporary root unavailable');
$temporary = $temporaryRoot . '/peanut-edition-upgrade-' . bin2hex(random_bytes(6));
$project = $temporary . '/project';
$package = $temporary . '/package';
mkdir($project . '/.peanut/scaffold-baseline/3.0.11/files/scripts/scaffold-runtime', 0775, true);
mkdir($package, 0775, true);

try {
    $old = [
        'managed.txt' => "old managed\n",
        'scripts/scaffold-upgrade' => "<?php // old cli\n",
        'scripts/scaffold-runtime/EditionUpgradePackage.php' => "<?php // old loader\n",
    ];
    $files = [];
    foreach ($old as $path => $contents) {
        editionUpgradeFile($project . '/' . $path, $contents, $path === 'scripts/scaffold-upgrade' ? 0755 : 0644);
        editionUpgradeFile($project . '/.peanut/scaffold-baseline/3.0.11/files/' . $path, $contents);
        $files[] = [
            'path' => $path,
            'sha256' => hash('sha256', $contents),
            'mode' => $path === 'scripts/scaffold-upgrade' ? 0755 : 0644,
            'classification' => 'managed',
            'owner' => 'scaffold',
            'source' => $path,
            'baseline_path' => '.peanut/scaffold-baseline/3.0.11/files/' . $path,
        ];
    }
    editionUpgradeFile($project . '/business.php', "<?php // user business\n");
    editionUpgradeFile($project . '/server/.env', "APP_KEY=do-not-touch\n");
    editionUpgradeFile($project . '/server/app/Modules/ThirdParty/Custom.php', "<?php // third-party module\n");
    $files[] = [
        'path' => 'business.php',
        'sha256' => hash('sha256', "<?php // user business\n"),
        'mode' => 0644,
        'classification' => 'app-owned',
        'owner' => 'application',
        'source' => 'business.php',
    ];
    usort($files, static fn(array $left, array $right): int => strcmp($left['path'], $right['path']));
    $managedRows = [];
    foreach ($files as $file) if ($file['classification'] === 'managed') $managedRows[] = $file['path'] . "\0" . $file['sha256'];
    sort($managedRows, SORT_STRING);
    editionUpgradeJson($project . '/.peanut/application-manifest.json', [
        'schema_version' => 2,
        'protocol' => 'peanut.application-scaffold.v2',
        'application' => [
            'name' => 'Acme App', 'slug' => 'acme-app', 'package_identity' => 'acme/app',
            'version' => '1.4.0', 'profile' => 'full', 'edition' => 'standalone',
        ],
        'edition' => ['name' => 'standalone'],
        'template' => [
            'version' => '3.0.11', 'inventory_sha256' => str_repeat('c', 64),
            'source_commit' => str_repeat('a', 40), 'source_tree' => str_repeat('b', 40),
        ],
        'ownership' => [
            'baseline_root' => '.peanut/scaffold-baseline/3.0.11/files',
        ],
        'digests' => [
            'managed_tree_sha256' => hash('sha256', implode("\n", $managedRows)),
            'app_owned_tree_sha256' => hash('sha256', "business.php\0" . hash('sha256', "<?php // user business\n")),
        ],
        'files' => $files,
    ]);

    $targetContents = [
        'managed.txt' => "new managed\n",
        'scripts/scaffold-upgrade' => "<?php // new cli\n",
        'scripts/scaffold-runtime/EditionUpgradePackage.php' => "<?php // new loader\n",
        'server/database/migrations/20260830-edition-upgrade.sql' => "SELECT 1;\n",
    ];
    $targetFiles = [];
    foreach ($targetContents as $path => $contents) {
        editionUpgradeFile($package . '/target/files/' . $path, $contents, $path === 'scripts/scaffold-upgrade' ? 0755 : 0644);
        $targetFiles[] = [
            'path' => $path,
            'source' => 'files/' . $path,
            'template_sha256' => hash('sha256', $contents),
            'classification' => 'managed',
            'transform' => 'tokens',
            'mode' => $path === 'scripts/scaffold-upgrade' ? 0755 : 0644,
            'policy' => 'managed',
            'owner' => str_starts_with($path, 'server/') ? 'backend' : 'host',
        ];
    }
    usort($targetFiles, static fn(array $left, array $right): int => strcmp($left['path'], $right['path']));
    $targetTree = str_repeat('f', 64);
    $targetRelease = [
        'schema_version' => 3,
        'protocol' => 'peanut.scaffold-release.v3',
        'application' => ['version' => '0.1.0'],
        'release' => [
            'version' => '3.0.12',
            'source_commit' => str_repeat('d', 40),
            'source_tree' => str_repeat('e', 40),
            'inventory_sha256' => str_repeat('1', 64),
            'inventory_template_version' => '3.0.12',
            'managed_tree_sha256' => $targetTree,
            'tokens' => [
                'product_name' => '__TARGET_NAME__',
                'slug' => '__TARGET_SLUG__',
                'package_identity' => '__TARGET_PACKAGE__',
                'application_version' => '__TARGET_VERSION__',
            ],
        ],
        'files' => $targetFiles,
        'renames' => [],
        'edition' => ['name' => 'standalone'],
    ];
    editionUpgradeJson($package . '/target/scaffold-manifest.json', $targetRelease);
    editionUpgradeFile($package . '/upgrader/scripts/scaffold-upgrade', "<?php // package cli\n", 0755);
    editionUpgradeFile($package . '/upgrader/scripts/scaffold-runtime/EditionUpgradePackage.php', "<?php // package loader\n");
    $upgradeManifest = [
        'schema_version' => 1,
        'protocol' => 'peanut.edition-upgrade-package.v1',
        'product' => ['name' => 'Peanut Admin'],
        'edition' => ['name' => 'standalone'],
        'compatibility' => [
            'source' => ['minimum_inclusive' => '3.0.10', 'maximum_exclusive' => '3.0.12'],
            'major_policy' => 'same-major', 'edition_conversion' => false,
        ],
        'build_source' => [
            'commit' => str_repeat('d', 40), 'tree' => str_repeat('e', 40), 'inventory_sha256' => str_repeat('1', 64),
        ],
        'target' => [
            'version' => '3.0.12',
            'scaffold_manifest' => 'target/scaffold-manifest.json',
            'scaffold_manifest_sha256' => hash_file('sha256', $package . '/target/scaffold-manifest.json'),
            'managed_tree_sha256' => $targetTree,
        ],
        'upgrader' => ['entrypoint' => 'upgrader/scripts/scaffold-upgrade'],
        'migration_chain' => [
            'strategy' => 'append-only-ledger',
            'files' => [[
                'path' => 'server/database/migrations/20260830-edition-upgrade.sql',
                'sha256' => hash('sha256', "SELECT 1;\n"),
            ]],
        ],
        'ownership' => [
            'automatic' => ['managed', 'generated-managed'],
            'preserved' => ['app-owned', 'third-party-module', 'secret'],
        ],
        'recovery' => ['managed_files' => 'scaffold-recovery-plan', 'database' => 'operator-backup-required'],
        'signing' => ['algorithm' => 'ed25519', 'authority' => 'test-release'],
    ];
    editionUpgradeJson($package . '/upgrade-manifest.json', $upgradeManifest);

    $inventory = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($package, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile()) $inventory[str_replace('\\', '/', substr($file->getPathname(), strlen($package) + 1))] = hash_file('sha256', $file->getPathname());
    }
    ksort($inventory, SORT_STRING);
    $inventoryBytes = '';
    foreach ($inventory as $path => $digest) $inventoryBytes .= $path . "\0" . $digest . "\n";
    editionUpgradeFile($package . '/META-INF/files.sha256', $inventoryBytes);
    $keypair = sodium_crypto_sign_keypair();
    $public = sodium_crypto_sign_publickey($keypair);
    $secret = sodium_crypto_sign_secretkey($keypair);
    editionUpgradeJson($package . '/META-INF/signatures/test-release.json', [
        'schema_version' => 1,
        'algorithm' => 'ed25519',
        'key_id' => 'test-release',
        'inventory_sha256' => hash('sha256', $inventoryBytes),
        'signature_base64' => base64_encode(sodium_crypto_sign_detached(hash('sha256', $inventoryBytes, true), $secret)),
    ]);
    putenv('PEANUT_UPGRADE_TRUSTED_KEYS_JSON=' . json_encode(['test-release' => base64_encode($public)], JSON_THROW_ON_ERROR));

    $prepared = (new EditionUpgradePackage())->prepare($project, $package, 'test-release');
    $runner = new ScaffoldUpgradeRunner();
    editionUpgradeFile($project . '/managed.txt', "user managed customization\n");
    $blocked = $runner->preview($project, $prepared['from_manifest'], $prepared['to_manifest']);
    editionUpgradeExpect(
        $blocked['status'] === 'blocked'
            && count($blocked['impact']['must_resolve']) === 1
            && str_contains($blocked['impact']['message'], 'No files will be changed'),
        'managed conflict must have a business-readable stop plan',
    );
    editionUpgradeFile($project . '/managed.txt', $old['managed.txt']);
    $plan = $runner->preflight($project, $prepared['from_manifest'], $prepared['to_manifest']);
    editionUpgradeExpect(
        $plan['status'] === 'ready'
            && $plan['summary']['conflicts'] === 0
            && $plan['impact']['must_resolve'] === []
            && str_contains($plan['impact']['ownership_notice'], 'third-party Modules'),
        'package plan must be ready and explain the protected ownership boundary',
    );
    $businessDigest = hash_file('sha256', $project . '/business.php');
    $secretDigest = hash_file('sha256', $project . '/server/.env');
    $thirdPartyDigest = hash_file('sha256', $project . '/server/app/Modules/ThirdParty/Custom.php');
    $planPath = $project . '/' . $plan['plan_path'];
    editionUpgradeExpect($runner->apply($project, $planPath)['status'] === 'applied', 'package apply failed');
    editionUpgradeExpect($runner->verify($project, $planPath)['status'] === 'verified', 'package verify failed');
    editionUpgradeExpect((string)file_get_contents($project . '/managed.txt') === "new managed\n", 'managed target not applied');
    editionUpgradeExpect(hash_equals((string)$businessDigest, (string)hash_file('sha256', $project . '/business.php')), 'app-owned file changed');
    editionUpgradeExpect(hash_equals((string)$secretDigest, (string)hash_file('sha256', $project . '/server/.env')), 'secret changed');
    editionUpgradeExpect(hash_equals((string)$thirdPartyDigest, (string)hash_file('sha256', $project . '/server/app/Modules/ThirdParty/Custom.php')), 'third-party Module changed');
    $appliedManifest = json_decode((string)file_get_contents($project . '/.peanut/application-manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach ($appliedManifest['files'] as $file) {
        if (in_array($file['classification'], ['managed', 'generated-managed'], true)) {
            editionUpgradeExpect(isset($file['baseline_sha256']), 'upgraded managed file lost its baseline digest');
        }
    }
    editionUpgradeExpect($runner->recover($project, $planPath)['status'] === 'recovered', 'package recovery failed');
    editionUpgradeExpect((string)file_get_contents($project . '/managed.txt') === "old managed\n", 'managed recovery did not restore the source');
    editionUpgradeExpect(!is_file($project . '/server/database/migrations/20260830-edition-upgrade.sql'), 'recovery retained a target-only migration');
    editionUpgradeExpect(hash_equals((string)$businessDigest, (string)hash_file('sha256', $project . '/business.php')), 'recovery changed app-owned file');
    editionUpgradeExpect(hash_equals((string)$secretDigest, (string)hash_file('sha256', $project . '/server/.env')), 'recovery changed secret');
    editionUpgradeExpect(hash_equals((string)$thirdPartyDigest, (string)hash_file('sha256', $project . '/server/app/Modules/ThirdParty/Custom.php')), 'recovery changed third-party Module');

    editionUpgradeFile($package . '/target/files/managed.txt', "tampered\n");
    editionUpgradeFails(fn() => (new EditionUpgradePackage())->prepare($project, $package, 'test-release'), 'EDITION_UPGRADE_FILE_DIGEST_MISMATCH');
} finally {
    putenv('PEANUT_UPGRADE_TRUSTED_KEYS_JSON');
    editionUpgradeRemove($temporary);
}

echo "EDITION-UPGRADE-PACKAGE-001 passed\n";
