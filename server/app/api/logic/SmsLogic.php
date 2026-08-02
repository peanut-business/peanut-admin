<?php
declare(strict_types=1);

namespace app\api\logic;

use app\common\enum\notice\NoticeSceneEnum;
use app\common\logic\BaseLogic;
use app\common\model\member\Member;
use app\common\service\notice\VerificationCodeService;

class SmsLogic extends BaseLogic
{
    public static function sendCode(array $params): bool
    {
        $scene = (string) $params['scene'];
        $mobile = (string) $params['mobile'];

        if ($scene === NoticeSceneEnum::RESET_PASSWORD
            && !Member::where('mobile', $mobile)->count()) {
            self::setError('手机号未绑定账号');
            return false;
        }

        $service = new VerificationCodeService();
        if (!$service->send($scene, $mobile)) {
            self::setError($service->getError());
            return false;
        }
        return true;
    }
}
