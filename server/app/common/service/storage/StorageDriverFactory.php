<?php
declare(strict_types=1);
namespace app\common\service\storage;

use app\common\execution\ExecutionContextAccess;
use app\common\service\http\OutboundHttpTransport;
use app\common\service\storage\driver\AliyunStorageDriver;
use app\common\service\storage\driver\LocalStorageDriver;
use app\common\service\storage\driver\QcloudStorageDriver;
use app\common\service\storage\driver\QiniuStorageDriver;
use app\common\service\runtime\OperationalLog;

final class StorageDriverFactory
{
    public function __construct(
        private readonly StorageCredentialResolver $credentials,
        private readonly OutboundHttpTransport $http,
        private readonly AliyunStorageClientFactory $aliyun,
        private readonly QcloudStorageClientFactory $qcloud,
        private readonly ExecutionContextAccess $contexts,
    ) {
    }

    public function make(array $account, array $space): StorageDriver
    {
        $provider = (string)($account['driver'] ?? '');
        try {
            if ($provider !== 'local') {
                $account['resolved_credentials'] = $this->credentials->resolve($account);
            }
            $driver = match ($provider) {
                'local' => new LocalStorageDriver($space),
                'qiniu' => new QiniuStorageDriver($account, $space, $this->http),
                'aliyun' => new AliyunStorageDriver($account, $space, $this->aliyun),
                'qcloud' => new QcloudStorageDriver($account, $space, $this->qcloud),
                default => throw new \RuntimeException('存储驱动未注册'),
            };
        } catch (StorageProviderException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            OperationalLog::warning($this->contexts, 'storage_provider_unconfigured', [
                'provider' => $provider !== '' ? $provider : 'unknown',
                'exception' => $exception::class,
            ]);
            throw StorageProviderException::unconfigured($exception);
        }
        return new ObservedStorageDriver($provider, $driver, $this->contexts);
    }
}
