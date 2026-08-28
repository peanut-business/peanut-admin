<?php
declare(strict_types=1);

namespace app\common\service\storage;

interface StorageDriver
{
    public function put(string $objectKey, string $sourcePath): void;

    public function delete(string $objectKey): void;

    public function downloadTo(string $objectKey, string $targetPath): void;

    public function localPath(string $objectKey): ?string;
}
