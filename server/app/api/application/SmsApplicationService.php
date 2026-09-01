<?php
declare(strict_types=1);

namespace app\api\application;

use app\Modules\Official\Notification\Contracts\VerificationCodeCommands;
use app\Modules\Official\Member\Contracts\MemberIdentityCommands;
use app\common\enum\notice\NoticeSceneEnum;
use app\common\application\ApplicationService;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

class SmsApplicationService extends ApplicationService
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
            try {
                $this->memberIdentities->assertMobileBound($context, $mobile);
            } catch (\Throwable $e) {
                self::setError($e->getMessage());
                return false;
            }
        }

        $result = $this->verificationCodes->sendCode($context, $scene, $mobile);
        if (!$result->success) {
            self::setError($result->error);
            return false;
        }
        return true;
    }
}
