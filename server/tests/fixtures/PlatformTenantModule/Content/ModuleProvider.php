<?php
declare(strict_types=1);

namespace Fixture\Fixture\Content;

use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;

require_once dirname(__DIR__, 4) . '/vendor/autoload.php';

final class ModuleProvider implements ModuleProviderContract
{
    public function moduleKey(): string
    {
        return 'fixture.content';
    }

    public function bindings(): array
    {
        return [];
    }
}
