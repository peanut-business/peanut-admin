<?php
declare(strict_types=1);

namespace app\common\service\storage;

interface StorageDriver
{
    public function put(string $objectKey, string $sourcePath): void;

    public function delete(string $objectKey): void;

    public function publicUrl(string $objectKey): string;

    public function temporaryUrl(string $objectKey, int $expiresIn, string $filename, string $disposition): string;

    public function localPath(string $objectKey): ?string;
}
