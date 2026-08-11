<?php
declare(strict_types=1);

namespace app\common\service {
    final class ConfigService
    {
        public static function get(string $type, string $name, mixed $default = ''): mixed
        {
            return $type === 'storage' && $name === 'default' ? 'qiniu' : $default;
        }
    }
}

namespace app\common\service\storage {
    final class Driver
    {
        public static function engineConfig(string $engineName): array
        {
            return [
                'qiniu' => ['domain' => 'qiniu.example.test'],
                'aliyun' => ['domain' => 'https://aliyun.example.test/assets'],
            ][$engineName] ?? [];
        }
    }
}

namespace {
    final class FileMediaRequestStub
    {
        public function domain(): string
        {
            return 'https://admin.example.test';
        }
    }

    function request(): FileMediaRequestStub
    {
        return new FileMediaRequestStub();
    }

    function expectFileMedia(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    $serverRoot = dirname(__DIR__, 2);
    $repositoryRoot = dirname($serverRoot);

    require $serverRoot . '/app/common/service/FileService.php';

    $apiEvidence = json_decode((string)file_get_contents(
        $repositoryRoot . '/output/playwright/m02/api-db-summary.json'
    ), true, 512, JSON_THROW_ON_ERROR);
    $browserEvidence = json_decode((string)file_get_contents(
        $repositoryRoot . '/output/playwright/m02/browser-summary.json'
    ), true, 512, JSON_THROW_ON_ERROR);
    $storageEvidence = json_decode((string)file_get_contents(
        $repositoryRoot . '/output/playwright/s01/core-summary.json'
    ), true, 512, JSON_THROW_ON_ERROR);

    expectFileMedia(($apiEvidence['result'] ?? null) === 'passed', 'sealed M02 API/DB evidence must pass');
    foreach ([
        'category_tree_and_descendant_query',
        'image_video_file_upload',
        'wrong_extension_no_write',
        'cross_type_move_rejected_without_change',
        'file_and_storage_delete',
        'category_subtree_cascade',
        'permission_grant_and_revoke',
        'storage_column_and_composite_index',
    ] as $check) {
        expectFileMedia(($apiEvidence['assertions'][$check] ?? false) === true, 'missing M02 check: ' . $check);
    }
    foreach ($apiEvidence['cleanup'] ?? [] as $name => $count) {
        expectFileMedia($count === 0, 'M02 cleanup must be zero: ' . $name);
    }
    expectFileMedia(($browserEvidence['result'] ?? null) === 'passed', 'sealed M02 browser evidence must pass');
    expectFileMedia(($browserEvidence['fixtures_cleaned'] ?? false) === true, 'M02 browser fixtures must be clean');
    expectFileMedia(($storageEvidence['status'] ?? null) === 'passed', 'sealed S01 storage evidence must pass');
    expectFileMedia(
        ($storageEvidence['checks']['invalid_storage_engine_switch_rejected'] ?? false) === true,
        'invalid storage switch evidence missing'
    );
    expectFileMedia(($storageEvidence['configuration_restored'] ?? false) === true, 'S01 configuration must be restored');

    expectFileMedia(
        \app\common\service\FileService::getFileUrl('storage/uploads/a.png', 'local')
            === 'https://admin.example.test/storage/uploads/a.png',
        'local URL mapping failed'
    );
    expectFileMedia(
        \app\common\service\FileService::getFileUrl('uploads/a.png')
            === 'https://qiniu.example.test/uploads/a.png',
        'default cloud URL mapping failed'
    );
    expectFileMedia(
        \app\common\service\FileService::getFileUrl('uploads/a.png', 'aliyun')
            === 'https://aliyun.example.test/assets/uploads/a.png',
        'explicit original provider URL mapping failed'
    );
    expectFileMedia(
        \app\common\service\FileService::getFileUrl('uploads/a.png', 'unknown') === '',
        'unknown explicit provider must fail closed'
    );
    expectFileMedia(
        \app\common\service\FileService::getFileUrl('https://cdn.example.test/a.png', 'unknown')
            === 'https://cdn.example.test/a.png',
        'absolute URL must remain unchanged'
    );

    $ownedFiles = [
        'app/common/service/FileService.php',
        'app/common/service/UploadService.php',
        'app/common/model/file/File.php',
        'app/adminapi/logic/file/FileLogic.php',
        'app/adminapi/logic/file/FileCateLogic.php',
        'app/common/service/storage/Driver.php',
    ];
    $sources = [];
    foreach ($ownedFiles as $relativePath) {
        $absolutePath = $serverRoot . '/' . $relativePath;
        expectFileMedia(is_file($absolutePath), 'missing application owner: ' . $relativePath);
        $sources[$relativePath] = (string)file_get_contents($absolutePath);
    }
    expectFileMedia(
        str_contains($sources['app/common/model/file/File.php'], "['storage']"),
        'File model must use row storage provenance'
    );
    expectFileMedia(
        str_contains($sources['app/common/service/UploadService.php'], 'getFileUrl($uri, $storage)'),
        'upload response must use stored provider'
    );
    expectFileMedia(
        str_contains($sources['app/adminapi/logic/file/FileLogic.php'], 'new Driver($storage'),
        'delete must use stored provider'
    );
    foreach ($sources as $relativePath => $source) {
        expectFileMedia(
            !str_contains($source, 'PeanutAdmin\\FileMedia'),
            'application file owner must not deep import core: ' . $relativePath
        );
        expectFileMedia(
            !str_contains($source, 'pa_file_object'),
            'application file owner must not bind core schema: ' . $relativePath
        );
    }

    echo "PB04-FILE-MEDIA-HOST-001 passed\n";
}
