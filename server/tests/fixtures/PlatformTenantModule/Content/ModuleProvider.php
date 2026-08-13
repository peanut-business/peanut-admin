<?php
declare(strict_types=1);

namespace Fixture\Fixture\Content;

use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;

final class ModuleProvider implements ModuleProviderContract
{
    public function moduleKey(): string
    {
        return 'fixture.content';
    }
}
