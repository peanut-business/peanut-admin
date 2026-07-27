<?php
declare(strict_types=1);

namespace app\common\service\storage\engine;

use OSS\OssClient;
use OSS\Core\OssException;

/**
 * 阿里云 OSS 存储引擎（aliyuncs/oss-sdk-php）。
 * config.domain 既作访问域名，也作 OssClient 的 endpoint。
 */
class Aliyun extends Server
{
    private array $config;

    public function __construct(array $config)
    {
        parent::__construct();
        $this->config = $config;
    }

    public function upload(string $saveDir): bool
    {
        try {
            $client = $this->client();
            $client->uploadFile(
                $this->config['bucket'] ?? '',
                $saveDir . '/' . $this->fileName,
                $this->getRealPath()
            );
            return true;
        } catch (OssException $e) {
            $this->error = $e->getMessage();
            return false;
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function delete(string $fileName): bool
    {
        try {
            $this->client()->deleteObject($this->config['bucket'] ?? '', ltrim($fileName, '/'));
            return true;
        } catch (OssException $e) {
            $this->error = $e->getMessage();
            return false;
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    private function client(): OssClient
    {
        return new OssClient(
            $this->config['access_key'] ?? '',
            $this->config['secret_key'] ?? '',
            $this->config['domain'] ?? '',
            true
        );
    }
}
