<?php
declare(strict_types=1);

namespace app\common\service\storage\engine;

use Qcloud\Cos\Client;

/**
 * 腾讯云 COS 存储引擎（qcloud/cos-sdk-v5）。
 * 需要额外的 region 配置。
 */
class Qcloud extends Server
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
            $this->client()->putObject([
                'Bucket' => $this->config['bucket'] ?? '',
                'Key'    => $saveDir . '/' . $this->fileName,
                'Body'   => fopen($this->getRealPath(), 'rb'),
            ]);
            return true;
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function delete(string $fileName): bool
    {
        try {
            $this->client()->deleteObject([
                'Bucket' => $this->config['bucket'] ?? '',
                'Key'    => ltrim($fileName, '/'),
            ]);
            return true;
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    private function client(): Client
    {
        return new Client([
            'region'      => $this->config['region'] ?? '',
            'credentials' => [
                'secretId'  => $this->config['access_key'] ?? '',
                'secretKey' => $this->config['secret_key'] ?? '',
            ],
        ]);
    }
}
