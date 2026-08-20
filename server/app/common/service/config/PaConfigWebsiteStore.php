<?php
declare(strict_types=1);

namespace app\common\service\config;

use app\common\contract\config\WebsiteConfigStore;
use app\common\service\ConfigService;
use PeanutAdmin\Settings\Contract\WebsiteConfigStore as CoreWebsiteConfigStore;

final class PaConfigWebsiteStore implements WebsiteConfigStore, CoreWebsiteConfigStore
{
    private const TYPE = 'website';

    public function read(): array
    {
        $values = ConfigService::get(self::TYPE);
        return is_array($values) ? $values : [];
    }

    public function replaceAtomically(array $values): void
    {
        ConfigService::setManyAtomic(self::TYPE, $values);
    }
}
