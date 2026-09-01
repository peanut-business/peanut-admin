<?php
declare(strict_types=1);

namespace app\common\service\storage;

final readonly class StorageService
{
    public const DELIVERY_URL_TTL = 600;

    public function __construct(
        private StorageRepository $repository,
        private StorageDriverFactory $drivers,
    ) {}

    public function storePath(
        int $tenantId,
        ?int $memberId,
        string $purpose,
        string $sourcePath,
        string $originalName,
        string $mediaType,
    ): array {
        if ($tenantId < 1 || !is_file($sourcePath) || !is_readable($sourcePath)) {
            throw new \InvalidArgumentException('待存储文件无效');
        }
        $access = StoragePurpose::accessType($purpose);
        $originalName = self::filename($originalName);
        $fileKey = 'file_' . bin2hex(random_bytes(16));
        $objectKey = StoragePath::objectKey(
            $tenantId,
            $purpose,
            $fileKey,
            (string)pathinfo($originalName, PATHINFO_EXTENSION),
        );
        $route = $this->repository->route($purpose, $access);
        $driver = $this->drivers->make($route, $route);
        $size = filesize($sourcePath);
        $sha256 = hash_file('sha256', $sourcePath);
        if (!is_int($size) || !is_string($sha256)) {
            throw new \RuntimeException('文件信息读取失败');
        }
        $this->repository->reserveObject([
            'file_key' => $fileKey,
            'tenant_id' => $tenantId,
            'purpose' => $purpose,
            'access_type' => $access,
            'storage_space_id' => (int)$route['space_id'],
            'object_key' => $objectKey,
            'disposition' => StoragePurpose::disposition($purpose),
            'original_name' => $originalName,
            'media_type' => $mediaType !== '' ? $mediaType : 'application/octet-stream',
            'size_bytes' => $size,
            'sha256' => $sha256,
            'created_by_member_id' => $memberId && $memberId > 0 ? $memberId : null,
        ]);
        try {
            $driver->put($objectKey, $sourcePath);
            if (!$this->repository->markObjectReady($tenantId, $fileKey)) {
                throw new \RuntimeException('文件对象账本未能切换到 ready');
            }
        } catch (\Throwable $error) {
            $deleteFailure = null;
            try {
                $driver->delete($objectKey);
            } catch (\Throwable $exception) {
                $deleteFailure = $exception;
            }
            if (!$this->repository->markObjectWriteFailed($tenantId, $fileKey)) {
                throw new \RuntimeException('文件对象账本未能记录 write_failed', 0, $error);
            }
            if ($deleteFailure !== null) {
                throw new \RuntimeException('文件对象写入失败且补偿删除失败，需按 file_key 清理', 0, $deleteFailure);
            }
            throw $error;
        }

        $object = $this->repository->deliverableObjectForTenant($tenantId, $fileKey);
        if ($object === null) {
            throw new \RuntimeException('文件对象当前不可交付');
        }
        return [
            'file_key' => $fileKey,
            'object_key' => $objectKey,
            'access_type' => $access,
            'url' => $this->url($object),
            'original_name' => $originalName,
        ];
    }

    public function publicUrl(string $reference): string
    {
        $reference = trim($reference);
        if ($reference === '') {
            return '';
        }
        $internal = $this->internalReference($reference);
        if ($internal !== null) {
            $object = $this->repository->publicObject($internal);
            return $object === null ? '' : $this->url($object);
        }
        if (preg_match('#^https?://#i', $reference) === 1) {
            return $reference;
        }
        $object = $this->repository->publicObject($reference);
        return $object === null ? '' : $this->url($object);
    }

    public function normalizePublicReference(int $tenantId, string $reference): string
    {
        $reference = trim($reference);
        if ($reference === '') {
            return '';
        }
        $internal = $this->internalReference($reference) ?? $reference;
        if (preg_match('/^file_[0-9a-f]{32}$/D', $internal) === 1) {
            $object = $this->repository->deliverableObjectForTenant($tenantId, $internal);
            if ($object === null || $object['access_type'] !== 'public') {
                throw new \RuntimeException('素材对象不属于当前租户');
            }
            return $internal;
        }
        $path = preg_match('#^https?://#i', $internal) === 1
            ? ltrim((string)(parse_url($internal, PHP_URL_PATH) ?? ''), '/')
            : ltrim($internal, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, 8);
        }
        $object = $this->repository->publicObject($path);
        if ($object !== null) {
            if ((int)$object['tenant_id'] !== $tenantId || $path !== (string)$object['object_key']) {
                throw new \RuntimeException('素材对象不属于当前租户');
            }
            return (string)$object['file_key'];
        }
        if (preg_match('#^https?://#i', $reference) === 1) {
            return $reference;
        }
        throw new \RuntimeException('素材对象不属于当前租户');
    }

    public function delete(int $tenantId, string $fileKey): void
    {
        $object = $this->repository->objectForTenant($tenantId, $fileKey);
        if ($object === null) {
            throw new \RuntimeException('文件对象不存在');
        }
        if (!$this->repository->archive($tenantId, $fileKey)) {
            throw new \RuntimeException('文件对象状态更新失败');
        }
        try {
            $this->drivers->make($object, $object)->delete((string)$object['object_key']);
        } catch (\Throwable $error) {
            $this->repository->restore($tenantId, $fileKey);
            throw $error;
        }
    }

    public function accessUrlForTenant(int $tenantId, string $fileKey): string
    {
        $object = $this->repository->deliverableObjectForTenant($tenantId, $fileKey);
        if ($object === null) {
            throw new \RuntimeException('文件对象不存在或不可用');
        }
        return $this->url($object);
    }

    /** @return array{path:string,filename:string,media_type:string,disposition:string,temporary:bool} */
    public function authorizedDownload(int $tenantId, string $fileKey, int $expires, string $signature): array
    {
        if ($expires < time() || $expires > time() + self::DELIVERY_URL_TTL + 30
            || !hash_equals($this->signature($tenantId, $fileKey, $expires), $signature)
        ) {
            throw new \RuntimeException('文件链接无效或已过期');
        }
        $object = $this->repository->deliverableObjectForTenant($tenantId, $fileKey);
        if ($object === null) {
            throw new \RuntimeException('文件不存在或不可用');
        }
        $driver = $this->drivers->make($object, $object);
        $path = $driver->localPath((string)$object['object_key']);
        $temporary = false;
        if ($path === null) {
            $path = tempnam(sys_get_temp_dir(), 'peanut-storage-delivery-');
            if (!is_string($path)) {
                throw new \RuntimeException('文件交付临时空间不可用');
            }
            $temporary = true;
            try {
                $driver->downloadTo((string)$object['object_key'], $path);
            } catch (\Throwable $error) {
                if (is_file($path)) {
                    unlink($path);
                }
                throw $error;
            }
        }
        if (!is_file($path) || !is_readable($path)) {
            if ($temporary && is_file($path)) {
                unlink($path);
            }
            throw new \RuntimeException('文件不存在或不可用');
        }
        return [
            'path' => $path,
            'filename' => (string)$object['original_name'],
            'media_type' => (string)$object['media_type'],
            'disposition' => (string)$object['disposition'],
            'temporary' => $temporary,
        ];
    }

    private function url(array $object): string
    {
        $expires = time() + self::DELIVERY_URL_TTL;
        $tenantId = (int)$object['tenant_id'];
        $fileKey = (string)$object['file_key'];
        return rtrim((string)request()->domain(), '/') . '/api/storage/delivery?' . http_build_query([
            'tenant_id' => $tenantId,
            'file_key' => $fileKey,
            'expires' => $expires,
            'signature' => $this->signature($tenantId, $fileKey, $expires),
        ]);
    }

    private function signature(int $tenantId, string $fileKey, int $expires): string
    {
        $secret = (string)config('jwt.secret', '');
        if (strlen($secret) < 32) {
            throw new \RuntimeException('文件签名配置无效');
        }
        return hash_hmac('sha256', $tenantId . '|' . $fileKey . '|' . $expires, $secret);
    }

    private function internalReference(string $reference): ?string
    {
        if (preg_match('/^file_[0-9a-f]{32}$/D', $reference) === 1) {
            return $reference;
        }
        $path = (string)(parse_url($reference, PHP_URL_PATH) ?? '');
        if ($path === '/api/storage/delivery') {
            parse_str((string)(parse_url($reference, PHP_URL_QUERY) ?? ''), $query);
            $fileKey = $query['file_key'] ?? null;
            return is_string($fileKey) && preg_match('/^file_[0-9a-f]{32}$/D', $fileKey) === 1
                ? $fileKey
                : null;
        }
        $path = ltrim($path !== '' ? $path : $reference, '/');
        return str_starts_with($path, 'storage/tenants/v1/') ? substr($path, 8) : null;
    }

    private static function filename(string $value): string
    {
        $value = trim(str_replace(["\0", '/', "\\"], '_', $value));
        if ($value === '') {
            throw new \InvalidArgumentException('文件名无效');
        }
        return mb_substr($value, 0, 255);
    }
}
