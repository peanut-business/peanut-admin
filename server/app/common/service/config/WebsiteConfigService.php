<?php
declare(strict_types=1);

namespace app\common\service\config;

use app\common\contract\config\WebsiteConfigStore;
use Closure;
use PeanutAdmin\Settings\Application\WebsiteConfigService as CoreWebsiteConfigService;
use PeanutAdmin\Settings\Contract\WebsiteConfigStore as CoreWebsiteConfigStore;

/** @deprecated Application compatibility bridge to the framework-neutral core service. */
final class WebsiteConfigService
{
    private CoreWebsiteConfigService $delegate;

    public function __construct(
        WebsiteConfigStore $store,
        Closure $urlForRead,
        Closure $urlForStorage,
        ?array $defaults = null,
    ) {
        $defaults ??= BrandDefaults::website();
        $coreStore = $store instanceof CoreWebsiteConfigStore
            ? $store
            : new class($store) implements CoreWebsiteConfigStore {
                public function __construct(private WebsiteConfigStore $store)
                {
                }

                public function read(): array
                {
                    return $this->store->read();
                }

                public function replaceAtomically(array $values): void
                {
                    $this->store->replaceAtomically($values);
                }
            };
        $this->delegate = new CoreWebsiteConfigService(
            $coreStore,
            $urlForRead,
            $urlForStorage,
            $defaults,
        );
    }

    /** @return array<string, string> */
    public function get(): array
    {
        return $this->delegate->get();
    }

    /** @param array<string, mixed> $params */
    public function save(array $params): void
    {
        $this->delegate->save($params);
    }

    /** @return list<string> */
    public static function fields(): array
    {
        return CoreWebsiteConfigService::fields();
    }
}
