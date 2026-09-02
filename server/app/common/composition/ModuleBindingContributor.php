<?php
declare(strict_types=1);

namespace app\common\composition;

use Closure;

/** A Module-owned, startup-only contribution to the application container. */
interface ModuleBindingContributor
{
    /** @return array<class-string, class-string|Closure> */
    public function bindings(): array;
}
