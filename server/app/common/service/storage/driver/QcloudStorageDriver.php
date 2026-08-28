<?php
declare(strict_types=1);

namespace app\common\service\storage\driver;

use app\common\service\storage\StorageDriver;
use app\common\service\storage\StoragePath;
use Qcloud\Cos\Client;

final readonly class QcloudStorageDriver implements StorageDriver
{
    public function __construct(private array $account, private array $space) {}

    public function put(string $objectKey, string $sourcePath): void
    {
        $stream = fopen($sourcePath, 'rb');
        if (!is_resource($stream)) {
            throw new \RuntimeException('待上传文件不可读');
        }
        try {
            $this->client()->putObject([
                'Bucket' => (string)$this->space['bucket'],
                'Key' => StoragePath::assertObjectKey($objectKey),
                'Body' => $stream,
                'ACL' => 'private',
            ]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function delete(string $objectKey): void
    {
        $this->client()->deleteObject([
            'Bucket' => (string)$this->space['bucket'],
            'Key' => StoragePath::assertObjectKey($objectKey),
        ]);
    }

    public function downloadTo(string $objectKey, string $targetPath): void
    {
        $this->client()->download(
            (string)$this->space['bucket'],
            StoragePath::assertObjectKey($objectKey),
            $targetPath,
        );
    }

    public function localPath(string $objectKey): ?string { return null; }

    private function client(): Client
    {
        $credentials = (array)($this->account['resolved_credentials'] ?? []);
        return new Client([
            'region' => (string)($this->space['region'] ?? ''),
            'credentials' => [
                'secretId' => (string)($credentials['access_key'] ?? ''),
                'secretKey' => (string)($credentials['secret_key'] ?? ''),
            ],
        ]);
    }
}
