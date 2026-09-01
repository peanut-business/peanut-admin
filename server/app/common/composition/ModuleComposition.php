<?php
declare(strict_types=1);

namespace app\common\composition;

use Closure;
use PeanutAdmin\Kernel\Module\CompiledModuleRegistry;
use PeanutAdmin\Kernel\Module\ModuleException;
use PeanutAdmin\Kernel\Module\ModuleProvider;
use think\App;

/** Registers compiled Module bindings into the one ThinkPHP container. */
final readonly class ModuleComposition
{
    public function __construct(private App $app)
    {
    }

    public function register(CompiledModuleRegistry $registry): void
    {
        $providers = [];
        $bindings = [];
        foreach ($registry->modules as $manifest) {
            $moduleKey = $manifest->data['key'] ?? null;
            $backend = $manifest->data['backend'] ?? null;
            $providerClass = is_array($backend) ? ($backend['provider'] ?? null) : null;
            if (!is_string($moduleKey) || $moduleKey === ''
                || !is_string($providerClass) || $providerClass === ''
                || isset($providers[$providerClass])) {
                throw new ModuleException('MODULE_COMPOSITION_INVALID', 'Module provider identity is invalid or duplicated.');
            }

            $provider = $this->app->make($providerClass, [], true);
            if (!$provider instanceof ModuleProvider
                || !$provider instanceof ModuleBindingContributor
                || !hash_equals($moduleKey, $provider->moduleKey())) {
                throw new ModuleException('MODULE_COMPOSITION_INVALID', "Module binding contributor is invalid: {$moduleKey}");
            }
            $providers[$providerClass] = true;

            foreach ($provider->bindings() as $abstract => $concrete) {
                if (!is_string($abstract) || $abstract === ''
                    || (!is_string($concrete) && !$concrete instanceof Closure)
                    || isset($bindings[$abstract])
                    || $this->app->bound($abstract)) {
                    throw new ModuleException('MODULE_BINDING_CONFLICT', "Duplicate or invalid Module binding: {$abstract}");
                }
                $bindings[$abstract] = $concrete;
            }
        }

        foreach ($bindings as $abstract => $concrete) {
            $this->app->bind($abstract, $concrete);
        }
    }
}
