<?php
declare(strict_types=1);

namespace app\Modules\Official\File;

use app\Modules\Official\File\Contracts\FileAdministration;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;

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
}
