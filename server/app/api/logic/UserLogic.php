<?php
declare(strict_types=1);

namespace app\api\logic;

use app\common\logic\BaseLogic;
use app\common\enum\notice\NoticeSceneEnum;
use app\common\model\member\Member;
use app\Modules\Official\Article\ModuleProvider as ArticleModuleProvider;
use app\common\service\member\AuthenticatedMemberContext;
use app\common\service\FileService;
use app\common\service\notice\VerificationCodeService;
use app\common\service\member\MemberTenantRepository;
use PeanutAdmin\Kernel\Module\ModuleException;

class UserLogic extends BaseLogic
{
    /** 用户中心（首屏数据） */
    public static function center(AuthenticatedMemberContext $context, int $memberId): array
    {
        $member = MemberTenantRepository::members($context)->field(['id', 'sn', 'nickname', 'avatar', 'mobile', 'user_money', 'points', 'create_time'])
            ->findOrEmpty($memberId);

        if ($member->isEmpty()) {
            return [];
        }

        $data               = $member->toArray();
        $data['balance']    = $data['user_money'];
        unset($data['user_money']);
        $data['avatar']     = FileService::getFileUrl((string) $data['avatar']);
        try {
            $data['collect_num'] = (new ArticleModuleProvider())->collectionSummary()
                ->countForMember($context, $memberId);
        } catch (ModuleException) {
            $data['collect_num'] = 0;
        }

        return $data;
    }

    /** 个人信息 */
    public static function info(AuthenticatedMemberContext $context, int $memberId): array
    {
        $member = MemberTenantRepository::members($context)->field(['id', 'sn', 'account', 'nickname', 'avatar', 'sex', 'birthday', 'mobile', 'email', 'user_money', 'points', 'create_time'])
            ->findOrEmpty($memberId);

        if ($member->isEmpty()) {
            return [];
        }

        $data           = $member->toArray();
        $data['balance'] = $data['user_money'];
        unset($data['user_money']);
        $data['avatar'] = FileService::getFileUrl((string) $data['avatar']);
        $data['has_password'] = $member->password !== '' && $member->password !== null;

        return $data;
    }

    /**
     * 更新用户信息
     * params: field(nickname|avatar|sex|birthday|email), value
     */
    public static function setInfo(AuthenticatedMemberContext $context, int $memberId, array $params): bool
    {
        try {
            $allowed = ['nickname', 'avatar', 'sex', 'birthday', 'email'];
            $field   = $params['field'] ?? '';

            if (!in_array($field, $allowed, true)) {
                throw new \Exception('不支持修改该字段');
            }

            $value = $params['value'];
            if ($field === 'avatar') {
                $value = FileService::setFileUrl((string) $value);
            }

            if (MemberTenantRepository::members($context)->where('id', $memberId)->update([$field => $value]) !== 1) {
                throw new \Exception('用户不存在');
            }
            return true;
        } catch (\Exception $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    /** 修改密码（需要旧密码） */
    public static function changePassword(AuthenticatedMemberContext $context, int $memberId, array $params): bool
    {
        try {
            $member = MemberTenantRepository::members($context)->where('id', $memberId)->findOrEmpty();
            if ($member->isEmpty()) {
                throw new \Exception('用户不存在');
            }

            [$hash, $salt] = array_pad(explode(':', (string) $member->password, 2), 2, '');
            if (md5(md5($params['old_password']) . $salt) !== $hash) {
                throw new \Exception('原密码错误');
            }

            $newSalt     = substr(md5((string) time()), 0, 8);
            $newPassword = md5(md5($params['password']) . $newSalt) . ':' . $newSalt;

            MemberTenantRepository::members($context)->where('id', $memberId)->update(['password' => $newPassword]);
            return true;
        } catch (\Exception $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    /** 绑定手机号 */
    public static function bindMobile(AuthenticatedMemberContext $context, int $memberId, array $params): bool
    {
        try {
            $mobile = $params['mobile'] ?? '';
            if (!preg_match('/^1[3-9]\d{9}$/', $mobile)) {
                throw new \Exception('手机号格式错误');
            }
            if (MemberTenantRepository::members($context)->where('mobile', $mobile)->where('id', '<>', $memberId)->count()) {
                throw new \Exception('手机号已被其他账号绑定');
            }

            $member = MemberTenantRepository::members($context)->where('id', $memberId)->findOrEmpty();
            if ($member->isEmpty()) {
                throw new \Exception('用户不存在');
            }
            $scene = empty($member->mobile)
                ? NoticeSceneEnum::BIND_MOBILE
                : NoticeSceneEnum::CHANGE_MOBILE;
            $service = new VerificationCodeService();
            if (!$service->verify($context, $scene, $mobile, (string) ($params['code'] ?? ''))) {
                throw new \Exception($service->getError());
            }
            MemberTenantRepository::members($context)->where('id', $memberId)->update(['mobile' => $mobile]);
            return true;
        } catch (\Exception $e) {
            self::setError($e->getMessage());
            return false;
        }
    }
}
