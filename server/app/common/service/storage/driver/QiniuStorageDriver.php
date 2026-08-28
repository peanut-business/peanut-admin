<?php
declare(strict_types=1);

namespace app\common\service\storage\driver;

use app\common\service\storage\StorageDriver;
use app\common\service\storage\StoragePath;
use GuzzleHttp\Client;
use Qiniu\Auth;
use Qiniu\Storage\BucketManager;
use Qiniu\Storage\UploadManager;

final readonly class QiniuStorageDriver implements StorageDriver
{
    public function __construct(private array $account, private array $space) {}

    public function put(string $objectKey, string $sourcePath): void
    {
        [, $error] = (new UploadManager())->putFile(
            $this->auth()->uploadToken((string)$this->space['bucket']),
            StoragePath::assertObjectKey($objectKey),
            $sourcePath,
        );
        if ($error) {
            throw new \RuntimeException($error->message());
        }
    }

    public function delete(string $objectKey): void
    {
        [, $error] = (new BucketManager($this->auth()))->delete(
            (string)$this->space['bucket'],
            StoragePath::assertObjectKey($objectKey),
        );
        if ($error) {
            throw new \RuntimeException($error->message());
        }
    }

    public function downloadTo(string $objectKey, string $targetPath): void
    {
        $url = $this->auth()->privateDownloadUrl($this->base($objectKey), 60);
        (new Client(['http_errors' => true]))->request('GET', $url, [
            'allow_redirects' => false,
            'sink' => $targetPath,
        ]);
    }

    public function localPath(string $objectKey): ?string
    {
        return null;
    }

    private function auth(): Auth
    {
        $credentials = (array)($this->account['resolved_credentials'] ?? []);
        return new Auth(
            (string)($credentials['access_key'] ?? ''),
            (string)($credentials['secret_key'] ?? ''),
        );
    }

    private function base(string $objectKey): string
    {
        $domain = rtrim((string)($this->space['access_domain'] ?? ''), '/');
        if ($domain === '') {
            throw new \RuntimeException('七牛访问域名未配置');
        }
        return $domain . '/' . StoragePath::assertObjectKey($objectKey);
    }
}
