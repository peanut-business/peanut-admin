<?php
declare(strict_types=1);

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
        \app\common\service\FileService::getFileUrl('https://cdn.example.test/a.png')
            === 'https://cdn.example.test/a.png',
        'absolute URL must remain unchanged'
    );

    $ownedFiles = [
        'app/common/service/FileService.php',
        'app/Modules/Official/File/Application/FileUploadService.php',
        'app/Modules/Official/File/Contracts/FileUploads.php',
        'app/Modules/Official/File/ModuleProvider.php',
        'app/api/controller/UploadController.php',
        'app/Modules/Official/File/Http/Controller/UploadController.php',
        'app/Modules/Official/File/Model/File.php',
        'app/Modules/Official/File/Application/FileAdministrationService.php',
        'app/Modules/Official/File/Contracts/FileAdministration.php',
        'app/common/service/storage/StorageService.php',
        'app/common/service/storage/StorageRepository.php',
        'app/common/service/storage/StoragePurpose.php',
    ];
    $sources = [];
    foreach ($ownedFiles as $relativePath) {
        $absolutePath = $serverRoot . '/' . $relativePath;
        expectFileMedia(is_file($absolutePath), 'missing application owner: ' . $relativePath);
        $sources[$relativePath] = (string)file_get_contents($absolutePath);
    }
    expectFileMedia(
        !str_contains($sources['app/Modules/Official/File/Model/File.php'], 'getUrlAttr')
            && str_contains(
                $sources['app/Modules/Official/File/Application/FileAdministrationService.php'],
                "FileService::getFileUrl((string) (\$item['file_key'] ?? ''))",
            ),
        'File presentation URL must be resolved by the application boundary from the canonical object key'
    );
    expectFileMedia(
        !is_file($serverRoot . '/app/common/service/UploadService.php')
            && str_contains($sources['app/Modules/Official/File/Application/FileUploadService.php'], '$this->storage->storePath(')
            && str_contains($sources['app/Modules/Official/File/ModuleProvider.php'], 'FileUploads::class'),
        'upload must be owned and explicitly bound by the File Module'
    );
    expectFileMedia(
        !str_contains($sources['app/Modules/Official/File/Application/FileUploadService.php'], 'request()->file')
            && substr_count($sources['app/Modules/Official/File/Application/FileUploadService.php'], 'UploadedFile $uploaded') === 4,
        'FileUploadService must receive the framework UploadedFile explicitly'
    );
    foreach ([
        'app/api/controller/UploadController.php',
        'app/Modules/Official/File/Http/Controller/UploadController.php',
    ] as $controller) {
        expectFileMedia(
            str_contains($sources[$controller], "\$this->request->file('file')")
                && str_contains($sources[$controller], 'instanceof UploadedFile')
                && str_contains($sources[$controller], 'FileUploads $uploads')
                && !str_contains($sources[$controller], 'catch ('),
            $controller . ' must validate and pass its UploadedFile explicitly'
        );
    }
    expectFileMedia(
        str_contains($sources['app/Modules/Official/File/Application/FileAdministrationService.php'], '$this->storage->delete'),
        'delete must use the unified storage service'
    );
    expectFileMedia(
        str_contains($sources['app/common/service/storage/StorageService.php'], 'repository->route')
        && str_contains($sources['app/common/service/storage/StorageService.php'], 'objectForTenant')
        && str_contains($sources['app/common/service/storage/StorageRepository.php'], 'f.tenant_id=:tenant_id'),
        'storage object writes and reads must remain Tenant-bound'
    );
    expectFileMedia(
        str_contains($sources['app/common/service/storage/StoragePurpose.php'], "'material.image' => StorageAccess::PUBLIC")
        && str_contains($sources['app/common/service/storage/StoragePurpose.php'], "'export.xlsx' => StorageAccess::PRIVATE")
        && str_contains($sources['app/common/service/storage/StoragePurpose.php'], "'export.csv' => StorageAccess::PRIVATE"),
        'public/private purpose routing changed'
    );
    foreach ($sources as $relativePath => $source) {
        expectFileMedia(
            !str_contains($source, 'PeanutAdmin\\FileMedia'),
            'application file owner must not deep import core: ' . $relativePath
        );
    }

    echo "PB04-FILE-MEDIA-HOST-001 passed\n";
}
