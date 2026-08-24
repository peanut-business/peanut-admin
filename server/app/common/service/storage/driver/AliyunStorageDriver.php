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
        $this->client()->uploadFile((string)$this->space['bucket'], StoragePath::assertObjectKey($objectKey), $sourcePath);
    }

    public function delete(string $objectKey): void
    {
        $this->client()->deleteObject((string)$this->space['bucket'], StoragePath::assertObjectKey($objectKey));
    }

    public function publicUrl(string $objectKey): string
    {
        $domain = rtrim((string)($this->space['access_domain'] ?? ''), '/');
        if ($domain === '') {
            throw new \RuntimeException('阿里云访问域名未配置');
        }
        return $domain . '/' . StoragePath::assertObjectKey($objectKey);
    }

    public function temporaryUrl(string $objectKey, int $expiresIn, string $filename, string $disposition): string
    {
        return $this->client()->signUrl(
            (string)$this->space['bucket'],
            StoragePath::assertObjectKey($objectKey),
            $expiresIn,
            OssClient::OSS_HTTP_GET,
            [OssClient::OSS_CONTENT_DISPOSITION => sprintf('%s; filename="%s"', $disposition, addslashes($filename))],
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
