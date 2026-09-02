<?php
declare(strict_types=1);

namespace app\common\service\config;

use PeanutAdmin\Kernel\Context\AuthenticatedMemberContext;
use app\common\service\tenant\TenantSettingService;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;
use PeanutAdmin\Settings\Contract\WebsiteConfigStore;

final class TenantSettingWebsiteStore implements WebsiteConfigStore
{
    public function __construct(
        private AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        private readonly TenantSettingService $settings,
    ) {
    }

    public function read(): array
    {
        return $this->settings->get(
            $this->context,
            'website',
            BrandDefaults::website(),
        )->document;
    }

    public function replaceAtomically(array $values): void
    {
        $this->settings->replace($this->context, 'website', $values);
    }
}
