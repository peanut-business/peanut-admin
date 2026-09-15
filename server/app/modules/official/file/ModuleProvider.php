<?php
declare(strict_types=1);

namespace app\modules\official\file;

use app\modules\official\file\services\fileAdministrationService;
use app\modules\official\file\services\fileUploadService;
use app\modules\official\file\contracts\fileAdministration;
use app\modules\official\file\contracts\fileUploads;
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
