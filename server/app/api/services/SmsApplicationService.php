<?php
declare(strict_types=1);

namespace app\api\services;

use app\modules\official\notification\contracts\VerificationCodeCommands;
use app\modules\official\member\contracts\MemberIdentityCommands;
use app\common\enum\notice\NoticeSceneEnum;
use app\common\exception\BusinessException;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

class SmsApplicationService
{
    public function __construct(
        private readonly MemberIdentityCommands $memberIdentities,
        private readonly VerificationCodeCommands $verificationCodes,
    ) {
    }

    public function sendCode(TenantContext|TenantSystemContext $context, array $params): bool
    {
        $scene = (string) $params['scene'];
        $mobile = (string) $params['mobile'];

        if ($scene === NoticeSceneEnum::RESET_PASSWORD) {
            $this->memberIdentities->assertMobileBound($context, $mobile);
        }

        $result = $this->verificationCodes->sendCode($context, $scene, $mobile);
        if (!$result->success) {
            throw BusinessException::conflict('VERIFICATION_CODE_SEND_REJECTED', $result->error);
        }
        return true;
    }
}
