<?php
declare(strict_types=1);

namespace app\platform\validation\ops;

use PeanutAdmin\Kernel\Context\PlatformContext;
use PeanutAdmin\Kernel\Platform\Authorization\PlatformAuthorizationEvaluator;
use PeanutAdmin\OpsConsole\Application\PlatformPermissionChecker;

/** Core Ops permission adapter over the application's canonical Platform RBAC. */
final readonly class PlatformOpsPermissionChecker implements PlatformPermissionChecker
{
    public function __construct(private PlatformAuthorizationEvaluator $evaluator) {}

    public function allows(PlatformContext $context, string $permissionKey): bool
    {
        return $this->evaluator->allows($context, $permissionKey);
    }
}
