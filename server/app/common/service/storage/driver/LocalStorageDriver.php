<?php
declare(strict_types=1);

namespace app\common\service\storage\driver;

use app\common\service\storage\StorageDriver;
use app\common\service\storage\StoragePath;

final readonly class LocalStorageDriver implements StorageDriver
{
    private string $root;

    public function __construct(private array $space)
    {
        $relative = (string)($space['local_path'] ?? '');
        if (!in_array($relative, ['public/storage', 'private/storage'], true)) {
            throw new \RuntimeException('本地存储空间配置无效');
        }
        $this->root = rtrim(app()->getRootPath(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    public function put(string $objectKey, string $sourcePath): void
    {
        $target = $this->path($objectKey);
        $directory = dirname($target);
        $mode = $this->space['access_type'] === 'private' ? 0700 : 0755;
        if ((!is_dir($directory) && !mkdir($directory, $mode, true)) || !copy($sourcePath, $target)) {
            throw new \RuntimeException('本地文件写入失败');
        }
        @chmod($target, $this->space['access_type'] === 'private' ? 0600 : 0644);
    }

    public function delete(string $objectKey): void
    {
        $path = $this->path($objectKey);
        if (is_file($path) && !unlink($path)) {
            throw new \RuntimeException('本地文件删除失败');
        }
    }

    public function downloadTo(string $objectKey, string $targetPath): void
    {
        if (!copy($this->path($objectKey), $targetPath)) {
            throw new \RuntimeException('本地文件读取失败');
        }
    }

    public function localPath(string $objectKey): ?string
    {
        return $this->path($objectKey);
    }

    private function path(string $objectKey): string
    {
        return $this->root . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, StoragePath::assertObjectKey($objectKey));
    }
}
