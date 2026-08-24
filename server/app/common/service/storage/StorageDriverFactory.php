<?php
declare(strict_types=1);
namespace app\common\service\storage;

use app\common\service\storage\driver\AliyunStorageDriver;
use app\common\service\storage\driver\LocalStorageDriver;
use app\common\service\storage\driver\QcloudStorageDriver;
use app\common\service\storage\driver\QiniuStorageDriver;

final class StorageDriverFactory
{
    public static function make(array $account, array $space, ?StorageCredentialResolver $resolver = null): StorageDriver
    {
        if ((string)($account['driver'] ?? '') !== 'local') {
            $account['resolved_credentials'] = ($resolver ?? new FailClosedStorageCredentialResolver())->resolve($account);
        }
        return match ((string)($account['driver'] ?? '')) {
            'local' => new LocalStorageDriver($space),
            'qiniu' => new QiniuStorageDriver($account, $space),
            'aliyun' => new AliyunStorageDriver($account, $space),
            'qcloud' => new QcloudStorageDriver($account, $space),
            default => throw new \RuntimeException('存储驱动未注册'),
        };
    }
}
