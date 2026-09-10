<?php
declare(strict_types=1);

namespace app\Modules\Official\File;

use app\Modules\Official\File\Application\FileAdministrationService;
use app\Modules\Official\File\Application\FileUploadService;
use app\Modules\Official\File\Contracts\FileAdministration;
use app\Modules\Official\File\Contracts\FileUploads;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;

final class ModuleProvider implements ModuleProviderContract
{
    public function moduleKey(): string
    {
        return 'official.file';
    }

    public function bindings(): array
    {
        return [
            FileAdministration::class => FileAdministrationService::class,
            FileUploads::class => FileUploadService::class,
        ];
    }
}
