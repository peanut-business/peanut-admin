<?php
declare(strict_types=1);

namespace app\common\service\storage\driver;

use app\common\service\storage\StorageDriver;
use app\common\service\storage\StoragePath;
use app\common\service\storage\AliyunStorageClientFactory;
use OSS\OssClient;

final readonly class AliyunStorageDriver implements StorageDriver
{
    private OssClient $client;

    public function __construct(
        array $account,
        private array $space,
        AliyunStorageClientFactory $clients,
    ) {
        $this->client = $clients->make($account, $space);
    }

    public function put(string $objectKey, string $sourcePath): void
    {
        $this->client->uploadFile(
            (string)$this->space['bucket'],
            StoragePath::assertObjectKey($objectKey),
            $sourcePath,
            [OssClient::OSS_HEADERS => [OssClient::OSS_OBJECT_ACL => OssClient::OSS_ACL_TYPE_PRIVATE]],
        );
    }

    public function delete(string $objectKey): void
    {
        $this->client->deleteObject((string)$this->space['bucket'], StoragePath::assertObjectKey($objectKey));
    }

    public function downloadTo(string $objectKey, string $targetPath): void
    {
        $this->client->getObject(
            (string)$this->space['bucket'],
            StoragePath::assertObjectKey($objectKey),
            [OssClient::OSS_FILE_DOWNLOAD => $targetPath],
        );
    }

    public function localPath(string $objectKey): ?string
    {
        return null;
    }

}
