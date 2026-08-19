<?php
declare(strict_types=1);

namespace app\common\service\config;

use app\common\contract\config\WebsiteConfigStore;
use app\common\service\member\AuthenticatedMemberContext;
use app\common\service\tenant\TenantSettingService;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

final class TenantSettingWebsiteStore implements WebsiteConfigStore
{
    public function __construct(
        private AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
    ) {
    }

    public function read(): array
    {
        return TenantSettingService::document(
            $this->context,
            'website',
            BrandDefaults::website(),
        );
    }

    public function replaceAtomically(array $values): void
    {
        TenantSettingService::replace($this->context, 'website', $values);
    }
}
