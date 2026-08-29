<?php
declare(strict_types=1);
namespace app\common\service\storage;

use app\common\service\http\OutboundHttpTransport;
use app\common\service\storage\driver\AliyunStorageDriver;
use app\common\service\storage\driver\LocalStorageDriver;
use app\common\service\storage\driver\QcloudStorageDriver;
use app\common\service\storage\driver\QiniuStorageDriver;
use app\common\service\runtime\OperationalLog;

final class StorageDriverFactory
{
    public static function make(array $account, array $space, ?StorageCredentialResolver $resolver = null): StorageDriver
    {
        $provider = (string)($account['driver'] ?? '');
        try {
            if ($provider !== 'local') {
                $account['resolved_credentials'] = ($resolver ?? new FailClosedStorageCredentialResolver())->resolve($account);
            }
            $driver = match ($provider) {
                'local' => new LocalStorageDriver($space),
                'qiniu' => new QiniuStorageDriver($account, $space, app(OutboundHttpTransport::class)),
                'aliyun' => new AliyunStorageDriver($account, $space, app(AliyunStorageClientFactory::class)),
                'qcloud' => new QcloudStorageDriver($account, $space, app(QcloudStorageClientFactory::class)),
                default => throw new \RuntimeException('存储驱动未注册'),
            };
        } catch (StorageProviderException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            OperationalLog::warning('storage_provider_unconfigured', [
                'provider' => $provider !== '' ? $provider : 'unknown',
                'exception' => $exception::class,
            ]);
            throw StorageProviderException::unconfigured($exception);
        }
        return new ObservedStorageDriver($provider, $driver);
    }
}
