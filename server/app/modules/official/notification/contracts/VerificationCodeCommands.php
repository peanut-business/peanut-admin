<?php
declare(strict_types=1);

namespace app\modules\official\notification\contracts;

use PeanutAdmin\Kernel\Context\AuthenticatedMemberContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

interface VerificationCodeCommands
{
    public function sendCode(
        TenantContext|TenantSystemContext $context,
        string $sceneCode,
        string $mobile
    ): DeliveryResult;

    public function verifyCode(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        string $sceneCode,
        string $mobile,
        string $code
    ): VerificationResult;
}
