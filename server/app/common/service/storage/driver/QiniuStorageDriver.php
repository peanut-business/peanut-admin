<?php
declare(strict_types=1);

namespace app\common\service\storage\driver;

use app\common\service\storage\StorageDriver;
use app\common\service\storage\StoragePath;
use app\common\service\http\OutboundHttpRequest;
use app\common\service\http\OutboundHttpTransport;
use Qiniu\Auth;

final readonly class QiniuStorageDriver implements StorageDriver
{
    private Auth $auth;

    public function __construct(
        array $account,
        private array $space,
        private OutboundHttpTransport $transport,
    ) {
        $credentials = (array)($account['resolved_credentials'] ?? []);
        $this->auth = new Auth(
            (string)($credentials['access_key'] ?? ''),
            (string)($credentials['secret_key'] ?? ''),
        );
    }

    public function put(string $objectKey, string $sourcePath): void
    {
        $objectKey = StoragePath::assertObjectKey($objectKey);
        $stream = fopen($sourcePath, 'rb');
        if (!is_resource($stream)) {
            throw new \RuntimeException('待上传文件不可读');
        }
        try {
            $response = $this->transport->send(new OutboundHttpRequest(
                method: 'POST',
                url: $this->uploadEndpoint(),
                timeoutSeconds: 120,
                multipart: [
                    ['name' => 'token', 'contents' => $this->auth->uploadToken((string)$this->space['bucket'])],
                    ['name' => 'key', 'contents' => $objectKey],
                    ['name' => 'file', 'contents' => $stream, 'filename' => basename($objectKey)],
                ],
            ));
        } finally {
            fclose($stream);
        }
        $payload = json_decode($response->body, true);
        if ($response->status < 200 || $response->status >= 300 || !is_array($payload)
            || trim((string)($payload['key'] ?? '')) === '') {
            throw new \RuntimeException('七牛对象上传失败');
        }
    }

    public function delete(string $objectKey): void
    {
        $entry = \Qiniu\entry(
            (string)$this->space['bucket'],
            StoragePath::assertObjectKey($objectKey),
        );
        $url = 'https://rs.qiniu.com/delete/' . $entry;
        $response = $this->transport->send(new OutboundHttpRequest(
            'POST',
            $url,
            $this->auth->authorization($url, null, 'application/x-www-form-urlencoded'),
            timeoutSeconds: 30,
        ));
        if ($response->status < 200 || $response->status >= 300) {
            throw new \RuntimeException('七牛对象删除失败');
        }
    }

    public function downloadTo(string $objectKey, string $targetPath): void
    {
        $url = $this->auth->privateDownloadUrl($this->base($objectKey), 60);
        $response = $this->transport->send(new OutboundHttpRequest(
            'GET',
            $url,
            retrySafe: true,
            sink: $targetPath,
        ));
        if ($response->status < 200 || $response->status >= 300) {
            throw new \RuntimeException('七牛对象下载失败');
        }
    }

    public function localPath(string $objectKey): ?string
    {
        return null;
    }

    private function base(string $objectKey): string
    {
        $domain = rtrim((string)($this->space['access_domain'] ?? ''), '/');
        if ($domain === '') {
            throw new \RuntimeException('七牛访问域名未配置');
        }
        return $domain . '/' . StoragePath::assertObjectKey($objectKey);
    }

    private function uploadEndpoint(): string
    {
        $endpoint = rtrim(trim((string)($this->space['endpoint'] ?? '')), '/');
        return $endpoint !== '' ? $endpoint : 'https://upload.qiniup.com';
    }
}
