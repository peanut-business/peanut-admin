<?php
declare(strict_types=1);

namespace app\Modules\Official\File;

use app\common\composition\ModuleBindingContributor;
use app\Modules\Official\File\Application\FileAdministrationService;
use app\Modules\Official\File\Application\FileUploadService;
use app\Modules\Official\File\Contracts\FileAdministration;
use app\Modules\Official\File\Contracts\FileUploads;
use app\common\execution\ExecutionContextAccess;
use app\common\service\FileService;
use app\common\service\storage\StorageService;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;
use think\App;

final class ModuleProvider implements ModuleProviderContract, ModuleBindingContributor
{
    public function moduleKey(): string
    {
        return 'official.file';
    }

    public function bindings(): array
    {
        return [
            FileAdministration::class => fn(App $app): FileAdministration => new FileAdministrationService(
                $app->make(\PeanutAdmin\Kernel\Persistence\TransactionManager::class),
                $app->make(StorageService::class),
                $app->make(ExecutionContextAccess::class),
                $app->make(FileService::class),
            ),
            FileUploads::class => fn(App $app): FileUploads => new FileUploadService(
                $app->make(StorageService::class),
                $app->make(ExecutionContextAccess::class),
            ),
        ];
    }
}
