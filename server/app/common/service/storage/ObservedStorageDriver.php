<?php
declare(strict_types=1);

namespace app\common\service\storage;

use app\common\execution\CurrentExecutionContext;
use app\common\service\runtime\OperationalLog;

/** Normalizes provider failures and emits only secret-free diagnostics. */
final readonly class ObservedStorageDriver implements StorageDriver
{
    public function __construct(
        private string $provider,
        private StorageDriver $delegate,
        private CurrentExecutionContext $executionContext,
    ) {
    }

    public function put(string $objectKey, string $sourcePath): void
    {
        $this->run('put', fn() => $this->delegate->put($objectKey, $sourcePath));
    }

    public function delete(string $objectKey): void
    {
        $this->run('delete', fn() => $this->delegate->delete($objectKey));
    }

    public function downloadTo(string $objectKey, string $targetPath): void
    {
        $this->run('download', fn() => $this->delegate->downloadTo($objectKey, $targetPath));
    }

    public function localPath(string $objectKey): ?string
    {
        return $this->run('local-path', fn(): ?string => $this->delegate->localPath($objectKey));
    }

    private function run(string $operation, callable $action): mixed
    {
        try {
            return $action();
        } catch (StorageProviderException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            OperationalLog::warning($this->executionContext, 'storage_provider_unavailable', [
                'provider' => $this->provider,
                'operation' => $operation,
                'exception' => $exception::class,
            ]);
            throw StorageProviderException::unavailable($exception);
        }
    }
}
