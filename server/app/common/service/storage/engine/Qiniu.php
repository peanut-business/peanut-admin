<?php
declare(strict_types=1);

namespace app\common\service\storage\engine;

use Qiniu\Auth;
use Qiniu\Storage\UploadManager;
use Qiniu\Storage\BucketManager;

/**
 * 七牛云存储引擎（qiniu/php-sdk）。
 * 云端对象 key = saveDir/fileName（不含 storage/ 前缀），
 * 可访问 URL 由 FileService 用配置 domain 拼接。
 */
class Qiniu extends Server
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
            $auth  = new Auth($this->config['access_key'] ?? '', $this->config['secret_key'] ?? '');
            $token = $auth->uploadToken($this->config['bucket'] ?? '');
            $key   = $saveDir . '/' . $this->fileName;

            $uploadMgr = new UploadManager();
            [, $err] = $uploadMgr->putFile($token, $key, $this->getRealPath());
            if ($err !== null) {
                $this->error = $err->message();
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function delete(string $fileName): bool
    {
        try {
            $auth      = new Auth($this->config['access_key'] ?? '', $this->config['secret_key'] ?? '');
            $bucketMgr = new BucketManager($auth);
            [, $err]   = $bucketMgr->delete($this->config['bucket'] ?? '', ltrim($fileName, '/'));
            if ($err !== null) {
                $this->error = $err->message();
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }
}
