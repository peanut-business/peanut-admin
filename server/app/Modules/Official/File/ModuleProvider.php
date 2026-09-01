<?php
declare(strict_types=1);

namespace app\Modules\Official\File;

use app\common\composition\ModuleBindingContributor;
use app\Modules\Official\File\Application\FileAdministrationService;
use app\Modules\Official\File\Contracts\FileAdministration;
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
            ),
        ];
    }
}
