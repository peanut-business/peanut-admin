<?php
declare(strict_types=1);

namespace app\api\logic;

use app\Modules\Official\Notification\ModuleProvider;
use app\common\enum\notice\NoticeSceneEnum;
use app\common\logic\BaseLogic;
use app\common\service\member\MemberTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

class SmsLogic extends BaseLogic
{
    public static function sendCode(TenantContext|TenantSystemContext $context, array $params): bool
    {
        $scene = (string) $params['scene'];
        $mobile = (string) $params['mobile'];

        if ($scene === NoticeSceneEnum::RESET_PASSWORD
            && !MemberTenantRepository::members($context)->where('mobile', $mobile)->count()) {
            self::setError('手机号未绑定账号');
            return false;
        }

        $result = (new ModuleProvider())->verification()->sendCode($context, $scene, $mobile);
        if (!$result->success) {
            self::setError($result->error);
            return false;
        }
        return true;
    }
}
