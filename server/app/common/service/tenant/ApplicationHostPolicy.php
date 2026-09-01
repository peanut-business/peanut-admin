<?php
declare(strict_types=1);

namespace app\common\service\tenant;

use PeanutAdmin\Kernel\Host\ApplicationHostPolicy as CoreApplicationHostPolicy;

final readonly class ApplicationHostPolicy
{
    /** @param list<string> $platformHosts @param list<string> $tenantAdminHosts */
    public function __construct(
        string $deploymentMode,
        array $platformHosts,
        array $tenantAdminHosts,
        TenantEntryBindingResolver $bindings,
    ) {
        $this->delegate = new CoreApplicationHostPolicy($deploymentMode, $platformHosts, $tenantAdminHosts, $bindings->delegate());
    }

    private CoreApplicationHostPolicy $delegate;

    public function assertPlatform(object $request): void
    {
        $this->delegate->assertPlatform($request);
    }

    public function assertTenantAdmin(object $request): void
    {
        $this->delegate->assertTenantAdmin($request);
    }
}
