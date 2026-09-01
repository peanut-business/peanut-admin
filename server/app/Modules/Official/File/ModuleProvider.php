<?php
declare(strict_types=1);

namespace app\Modules\Official\File;

use app\Modules\Official\File\Application\FileAdministrationService;
use app\Modules\Official\File\Contracts\FileAdministration;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;
use think\App;

final class ModuleProvider implements ModuleProviderContract
{
    public function moduleKey(): string
    {
        return 'official.file';
    }

    public function administration(): FileAdministration
    {
        return app(FileAdministration::class);
    }

    public function register(App $app): void
    {
        $app->bind(FileAdministration::class, FileAdministrationService::class);
    }
}
