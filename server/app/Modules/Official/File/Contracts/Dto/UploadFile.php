<?php
declare(strict_types=1);

namespace app\Modules\Official\File\Contracts\Dto;

final readonly class UploadFile
{
    public function __construct(
        public string $path,
        public string $originalName,
        public int $size,
        public string $mediaType,
        public string $extension,
    ) {}
}
