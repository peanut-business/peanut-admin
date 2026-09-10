<?php
declare(strict_types=1);

namespace app\common\composition;

use PeanutAdmin\Kernel\Module\CompiledModuleRegistry;
use PeanutAdmin\Kernel\Module\ModuleException;
use PeanutAdmin\Kernel\Module\ModuleProvider;
use PeanutAdmin\Kernel\Module\ModuleProviderBindings;
use think\App;

/** Registers compiled Module bindings into the one ThinkPHP container. */
final readonly class ModuleComposition
{
    public function __construct(private App $app)
    {
    }

    public function register(CompiledModuleRegistry $registry): void
    {
        $providerClasses = [];
        $providers = [];
        foreach ($registry->modules as $manifest) {
            $moduleKey = $manifest->data['key'] ?? null;
            $backend = $manifest->data['backend'] ?? null;
            $providerClass = is_array($backend) ? ($backend['provider'] ?? null) : null;
            if (!is_string($moduleKey) || $moduleKey === ''
                || !is_string($providerClass) || $providerClass === ''
                || isset($providerClasses[$providerClass])) {
                throw new ModuleException('MODULE_COMPOSITION_INVALID', 'Module provider identity is invalid or duplicated.');
            }

            $provider = $this->app->make($providerClass, [], true);
            if (!$provider instanceof ModuleProvider
                || !hash_equals($moduleKey, $provider->moduleKey())) {
                throw new ModuleException('MODULE_COMPOSITION_INVALID', "Module provider is invalid: {$moduleKey}");
            }
            $providerClasses[$providerClass] = true;
            $providers[] = $provider;
        }

        $bindings = ModuleProviderBindings::collect($providers);
        $this->assertAliasGraphIsAcyclic($bindings);
        foreach ($bindings as $abstract => $concrete) {
            if ($this->app->bound($abstract)) {
                throw new ModuleException('MODULE_BINDING_CONFLICT', "Module binding conflicts with Host binding: {$abstract}");
            }
        }
        foreach ($bindings as $abstract => $concrete) {
            $this->app->bind($abstract, $concrete);
        }
    }

    /** @param array<class-string, class-string|\Closure> $bindings */
    private function assertAliasGraphIsAcyclic(array $bindings): void
    {
        foreach ($bindings as $abstract => $concrete) {
            if (!is_string($concrete)) {
                continue;
            }
            $seen = [$abstract => true];
            $next = $concrete;
            while (isset($bindings[$next]) && is_string($bindings[$next])) {
                if (isset($seen[$next])) {
                    throw new ModuleException('MODULE_BINDING_CONFLICT', "Cyclic Module binding: {$abstract}");
                }
                $seen[$next] = true;
                $next = $bindings[$next];
            }
        }
    }
}
