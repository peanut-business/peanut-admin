<?php
declare(strict_types=1);

use app\platform\service\plugin\DeterministicTarArchive;
use app\platform\service\plugin\ModulePackagePreflight;
use app\platform\service\plugin\PluginPackageArchiveService;
use app\platform\service\plugin\PluginPackageException;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function modulePackageExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @param callable():void $operation */
function modulePackageRejects(callable $operation, string $errorCode): void
{
    try {
        $operation();
        throw new RuntimeException("Expected package rejection: {$errorCode}");
    } catch (PluginPackageException $exception) {
        modulePackageExpect($exception->errorCode === $errorCode, "Unexpected package rejection: {$exception->errorCode}");
    }
}

function modulePackageCopyTree(string $source, string $target): void
{
    mkdir($target, 0777, true);
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );
    foreach ($iterator as $entry) {
        $relative = substr($entry->getPathname(), strlen($source) + 1);
        $destination = $target . '/' . $relative;
        if ($entry->isDir()) {
            if (!is_dir($destination)) {
                mkdir($destination, 0777, true);
            }
        } else {
            copy($entry->getPathname(), $destination);
        }
    }
}

function modulePackageRemoveTree(string $path): void
{
    if (!is_dir($path)) {
        if (is_file($path)) {
            unlink($path);
        }
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $entry) {
        $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }
    rmdir($path);
}

function modulePackageRewriteTree(string $root, array $replacements): void
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $entry) {
        if (!$entry->isFile()) {
            continue;
        }
        $contents = (string)file_get_contents($entry->getPathname());
        file_put_contents($entry->getPathname(), str_replace(array_keys($replacements), array_values($replacements), $contents));
    }
}

$serverRoot = dirname(__DIR__, 2);
$projectRoot = dirname($serverRoot);
$temporary = sys_get_temp_dir() . '/pa-module-package-' . bin2hex(random_bytes(8));
mkdir($temporary, 0700, true);

try {
    $service = new PluginPackageArchiveService($serverRoot);
    $first = $temporary . '/fixture-a.tar';
    $second = $temporary . '/fixture-b.tar';
    $packedA = $service->packModule('fixture.delivery-record', $first);
    $packedB = $service->packModule('fixture.delivery-record', $second);
    modulePackageExpect($packedA['sha256'] === $packedB['sha256'], 'same Module tree did not produce a deterministic tar');

    $tar = new DeterministicTarArchive();
    $entries = $tar->scan($first);
    modulePackageExpect(isset($entries['META-INF/files.sha256']), 'package inventory is missing');
    $inventory = $tar->read($first, $entries['META-INF/files.sha256']);
    modulePackageExpect(str_contains($inventory, "\0"), 'package inventory does not use path+NUL+sha256 rows');
    modulePackageExpect(
        count(array_filter(array_keys($entries), static fn(string $path): bool => str_ends_with($path, '/module.json'))) === 1,
        'single-Module package contains a second manifest'
    );

    $verified = $service->verify($first, $packedA['sha256'], [], null, []);
    modulePackageExpect($verified->packageKey === 'fixture.delivery-record', 'verified package identity changed');
    modulePackageExpect($verified->dependencyOrder === ['fixture.delivery-record'], 'single-Module dependency order changed');
    $service->cleanup($verified);

    modulePackageRejects(
        static fn() => $service->verify($first, str_repeat('0', 64), [], null, []),
        'MODULE_PACKAGE_ARCHIVE_DIGEST_MISMATCH',
    );
    modulePackageRejects(
        static fn() => $service->verify($first, null, [], null, []),
        'MODULE_PACKAGE_SOURCE_UNTRUSTED',
    );

    $keypair = sodium_crypto_sign_keypair();
    $secret = sodium_crypto_sign_secretkey($keypair);
    $public = sodium_crypto_sign_publickey($keypair);
    $signedPath = $temporary . '/fixture-signed.tar';
    $service->packModule('fixture.delivery-record', $signedPath, ['key_id' => 'fixture-release', 'secret_key' => $secret]);
    $signed = $service->verify($signedPath, null, ['fixture-release' => $public], 'fixture-release', []);
    modulePackageExpect($signed->packageKey === 'fixture.delivery-record', 'signed package did not verify');
    $service->cleanup($signed);

    $tamperedEntries = [];
    foreach ($entries as $path => $entry) {
        $contents = $tar->read($first, $entry);
        if ($path === 'web/src/modules/fixture-delivery-record/contribution.ts') {
            $contents .= "\n// tampered\n";
        }
        $tamperedEntries[$path] = ['contents' => $contents];
    }
    $tamperedPath = $temporary . '/fixture-tampered.tar';
    $tar->write($tamperedPath, $tamperedEntries);
    modulePackageRejects(
        static fn() => $service->verify($tamperedPath, hash_file('sha256', $tamperedPath), [], null, []),
        'MODULE_PACKAGE_FILE_DIGEST_MISMATCH',
    );
    modulePackageRejects(
        static fn() => $tar->write($temporary . '/unsafe.tar', ['../escape' => ['contents' => 'x']]),
        'MODULE_PACKAGE_PATH_INVALID',
    );

    $fixtureBackend = $projectRoot . '/server/app/Modules/Fixture/DeliveryRecord';
    $fixtureFrontend = $projectRoot . '/web/src/modules/fixture-delivery-record';
    $badRoot = $temporary . '/bad-project';
    modulePackageCopyTree($fixtureBackend, $badRoot . '/server/app/Modules/Fixture/DeliveryRecord');
    modulePackageCopyTree($fixtureFrontend, $badRoot . '/web/src/modules/fixture-delivery-record');
    $badManifestPath = $badRoot . '/server/app/Modules/Fixture/DeliveryRecord/module.json';
    $badManifest = json_decode((string)file_get_contents($badManifestPath), true, 64, JSON_THROW_ON_ERROR);
    $badManifest['frontend']['entry'] = 'web/src/modules/fixture-delivery-record/index.ts';
    file_put_contents($badManifestPath, json_encode($badManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    modulePackageRejects(
        static fn() => (new ModulePackagePreflight($badRoot))->inspect('fixture.delivery-record'),
        'MODULE_PACKAGE_FRONTEND_ENTRY_MISMATCH',
    );

    $bundleRoot = $temporary . '/bundle-project';
    mkdir($bundleRoot . '/server/resources/schemas', 0777, true);
    copy($serverRoot . '/resources/schemas/plugin.schema.json', $bundleRoot . '/server/resources/schemas/plugin.schema.json');
    foreach ([
        'acme.first' => ['First', 'pa_acme_first'],
        'acme.second' => ['Second', 'pa_acme_second'],
    ] as $key => [$class, $table]) {
        $backend = $bundleRoot . '/server/app/Modules/Acme/' . $class;
        $frontend = $bundleRoot . '/web/src/modules/' . str_replace('.', '-', $key);
        modulePackageCopyTree($fixtureBackend, $backend);
        modulePackageCopyTree($fixtureFrontend, $frontend);
        modulePackageRewriteTree($backend, [
            'fixture.delivery-record' => $key,
            'fixture-delivery-record' => str_replace('.', '-', $key),
            'Fixture\\DeliveryRecord' => 'Acme\\' . $class,
            'peanut-business/fixture-delivery-record' => 'acme/' . strtolower($class),
            'pa_fixture_delivery_record' => $table,
        ]);
        modulePackageRewriteTree($frontend, [
            'fixture.delivery-record' => $key,
            'fixture-delivery-record' => str_replace('.', '-', $key),
            '@peanut-admin/fixture-delivery-record' => '@acme/' . strtolower($class),
        ]);
    }
    $firstManifestPath = $bundleRoot . '/server/app/Modules/Acme/First/module.json';
    $firstManifest = json_decode((string)file_get_contents($firstManifestPath), true, 64, JSON_THROW_ON_ERROR);
    $firstManifest['dependencies'] = [['module_key' => 'acme.second', 'version' => '^1.0']];
    file_put_contents($firstManifestPath, json_encode($firstManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    $bundleService = new PluginPackageArchiveService($bundleRoot . '/server');
    $bundlePath = $temporary . '/acme-bundle.tar';
    $bundleResult = $bundleService->packBundle('acme.bundle', '1.0.0', ['acme.first', 'acme.second'], $bundlePath);
    $bundleEntries = $tar->scan($bundlePath);
    $bundleExtracted = $temporary . '/bundle-extracted';
    $tar->extract($bundlePath, $bundleEntries, $bundleExtracted);
    $bundleFiles = [];
    foreach (['server/app/Modules/Acme/First', 'server/app/Modules/Acme/Second', 'web/src/modules/acme-first', 'web/src/modules/acme-second'] as $root) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($bundleExtracted . '/' . $root, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $entry) {
            if ($entry->isFile() && !$entry->isLink()) {
                $relative = substr($entry->getPathname(), strlen($bundleExtracted) + 1);
                $bundleFiles[$relative] = hash_file('sha256', $entry->getPathname());
            }
        }
    }
    ksort($bundleFiles, SORT_STRING);
    $bundleSourceFiles = [];
    foreach (['server/app/Modules/Acme/First', 'server/app/Modules/Acme/Second', 'web/src/modules/acme-first', 'web/src/modules/acme-second'] as $root) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($bundleRoot . '/' . $root, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $entry) {
            if ($entry->isFile() && !$entry->isLink()) {
                $relative = substr($entry->getPathname(), strlen($bundleRoot) + 1);
                $bundleSourceFiles[$relative] = hash_file('sha256', $entry->getPathname());
            }
        }
    }
    ksort($bundleSourceFiles, SORT_STRING);
    modulePackageExpect(
        $bundleFiles === $bundleSourceFiles,
        'bundle payload differs from source: ' . json_encode(array_diff_assoc($bundleSourceFiles, $bundleFiles), JSON_UNESCAPED_SLASHES)
    );
    $bundleCanonical = '';
    foreach ($bundleFiles as $relative => $digest) {
        $bundleCanonical .= $relative . "\0" . $digest . "\n";
    }
    $bundleManifest = json_decode((string)file_get_contents($bundleExtracted . '/plugins/acme.bundle/plugin.json'), true, 64, JSON_THROW_ON_ERROR);
    $bundleActualSourceDigest = hash('sha256', $bundleCanonical);
    modulePackageExpect(
        $bundleActualSourceDigest === $bundleManifest['source']['sha256'],
        'bundle source digest changed during archive round trip'
    );
    $bundle = $bundleService->verify($bundlePath, $bundleResult['sha256'], [], null, []);
    modulePackageExpect(
        $bundle->dependencyOrder === ['acme.second', 'acme.first'],
        'bundle dependency order is not topological'
    );
    modulePackageExpect(count($bundle->modules) === 2, 'bundle Module count changed');
    $bundleService->cleanup($bundle);

    echo "MODULE-PACKAGE-ARCHIVE-001 passed sha256={$packedA['sha256']}\n";
} finally {
    modulePackageRemoveTree($temporary);
}
