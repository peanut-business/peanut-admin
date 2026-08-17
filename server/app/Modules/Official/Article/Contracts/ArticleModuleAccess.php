<?php
declare(strict_types=1);

namespace app\Modules\Official\Article\Contracts;

use PeanutAdmin\Kernel\Auth\TenantContext;

interface ArticleModuleAccess
{
    public function assertTenant(int $tenantId): void;

    public function assertMember(TenantContext $context, string $permission, bool $rootBypass = false): void;
}
