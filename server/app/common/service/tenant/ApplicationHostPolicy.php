<?php
declare(strict_types=1);

namespace app\common\service\tenant;

use PeanutAdmin\Kernel\Host\ApplicationHostPolicy as CoreApplicationHostPolicy;
use think\facade\Config;

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

    public static function production(): self
    {
        return new self(
            trim((string)Config::get('deployment.mode', '')),
            CoreApplicationHostPolicy::hostList((string)Config::get('deployment.platform_hosts', '')),
            CoreApplicationHostPolicy::hostList((string)Config::get('deployment.tenant_admin_hosts', '')),
            TenantEntryBindingResolver::production(),
        );
    }

    public function assertPlatform(object $request): void
    {
        $this->delegate->assertPlatform($request);
    }

    public function assertTenantAdmin(object $request): void
    {
        $this->delegate->assertTenantAdmin($request);
    }
}
