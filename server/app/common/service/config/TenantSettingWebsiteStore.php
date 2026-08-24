<?php
declare(strict_types=1);

namespace app\common\service\config;

use app\common\contract\config\WebsiteConfigStore;
use app\common\service\member\AuthenticatedMemberContext;
use app\common\service\tenant\TenantSettingsRuntimeFactory;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;
use PeanutAdmin\Settings\Contract\WebsiteConfigStore as CoreWebsiteConfigStore;

final class TenantSettingWebsiteStore implements WebsiteConfigStore, CoreWebsiteConfigStore
{
    public function __construct(
        private AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
    ) {
    }

    public function read(): array
    {
        return TenantSettingsRuntimeFactory::service()->get(
            $this->context,
            'website',
            BrandDefaults::website(),
        )->document;
    }

    public function replaceAtomically(array $values): void
    {
        TenantSettingsRuntimeFactory::service()->replace($this->context, 'website', $values);
    }
}
