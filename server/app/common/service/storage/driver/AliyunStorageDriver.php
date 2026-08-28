<?php
declare(strict_types=1);

namespace app\common\service\storage\driver;

use app\common\service\storage\StorageDriver;
use app\common\service\storage\StoragePath;
use OSS\OssClient;

final readonly class AliyunStorageDriver implements StorageDriver
{
    public function __construct(private array $account, private array $space) {}

    public function put(string $objectKey, string $sourcePath): void
    {
        $this->client()->uploadFile(
            (string)$this->space['bucket'],
            StoragePath::assertObjectKey($objectKey),
            $sourcePath,
            [OssClient::OSS_HEADERS => [OssClient::OSS_OBJECT_ACL => OssClient::OSS_ACL_TYPE_PRIVATE]],
        );
    }

    public function delete(string $objectKey): void
    {
        $this->client()->deleteObject((string)$this->space['bucket'], StoragePath::assertObjectKey($objectKey));
    }

    public function downloadTo(string $objectKey, string $targetPath): void
    {
        $this->client()->getObject(
            (string)$this->space['bucket'],
            StoragePath::assertObjectKey($objectKey),
            [OssClient::OSS_FILE_DOWNLOAD => $targetPath],
        );
    }

    public function localPath(string $objectKey): ?string
    {
        return null;
    }

    private function client(): OssClient
    {
        $credentials = (array)($this->account['resolved_credentials'] ?? []);
        return new OssClient(
            (string)($credentials['access_key'] ?? ''),
            (string)($credentials['secret_key'] ?? ''),
            (string)$this->space['endpoint'],
            true,
        );
    }
}
