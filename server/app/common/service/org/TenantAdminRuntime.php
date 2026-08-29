<?php
declare(strict_types=1);

namespace app\common\service\org;

use app\common\service\ApplicationPasswordPolicy;
use app\common\service\DemoAccountPolicy;
use PDO;
use PeanutAdmin\Kernel\Identity\SelfService\AccountSelfService;
use PeanutAdmin\Kernel\Membership\Application\MemberAdminService;

/** Composition root for native Tenant administration services. */
final readonly class TenantAdminRuntime
{
    public function __construct(private PDO $pdo)
    {
    }

    public function members(): MemberAdminService
    {
        return new MemberAdminService($this->pdo, ApplicationPasswordPolicy::hasher());
    }

    public function selfService(): AccountSelfService
    {
        return new AccountSelfService($this->pdo, ApplicationPasswordPolicy::hasher());
    }

    public function assertPasswordChangeAllowed(int $accountId): void
    {
        DemoAccountPolicy::assertPasswordChangeAllowed($this->pdo, $accountId);
    }
}
