<?php
declare(strict_types=1);

namespace app\api\logic;

use app\Modules\Official\Notification\ModuleProvider;
use app\Modules\Official\Member\ModuleProvider as MemberModuleProvider;
use app\api\service\UserTokenService;
use app\common\logic\BaseLogic;
use app\common\enum\notice\NoticeSceneEnum;
use app\Modules\Official\Member\Model\Member;
use app\common\service\FileService;
use app\common\service\config\TenantApplicationSettingService;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

class LoginLogic extends BaseLogic
{
    /**
     * 账号注册
     * params: account, password, scene(默认h5)
     */
    public static function register(TenantSystemContext $context, array $params): bool
    {
        try {
            self::assertLoginWayEnabled($context, 1);
            (new MemberModuleProvider())->identityCommands()->register(
                $context,
                (string)$params['account'],
                (string)$params['password'],
                self::defaultAvatar($context),
            );

            return true;
        } catch (\Exception $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    /**
     * 账号/手机号 + 密码登录
     * params: account(账号或手机号), password, terminal
     */
    public static function login(TenantSystemContext $context, array $params): array|false
    {
        try {
            self::assertLoginWayEnabled($context, 1);
            $member = (new MemberModuleProvider())->identityCommands()->login(
                $context,
                (string)$params['account'],
                (string)$params['password'],
                request()->ip(),
            );

            $token  = UserTokenService::createToken($member->id);
            $avatar = FileService::getFileUrl((string) $member->avatar);

            return [
                'token'    => $token,
                'id'       => $member->id,
                'sn'       => $member->sn,
                'nickname' => $member->nickname,
                'avatar'   => $avatar,
                'mobile'   => $member->mobile,
            ];
        } catch (\Exception $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function mobileLogin(TenantContext|TenantSystemContext $context, array $params): array|false
    {
        try {
            self::assertLoginWayEnabled($context, 2);
            $mobile = (string) $params['mobile'];
            $result = (new ModuleProvider())->verification()->verifyCode(
                $context,
                NoticeSceneEnum::LOGIN_CODE,
                $mobile,
                (string) $params['code'],
            );
            if (!$result->accepted) {
                throw new \RuntimeException($result->error);
            }

            $member = (new MemberModuleProvider())->identityCommands()->loginByVerifiedMobile(
                $context,
                $mobile,
                self::defaultAvatar($context),
                request()->ip(),
            );
            return self::loginResult($member, false);
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function resetPassword(TenantContext|TenantSystemContext $context, array $params): bool
    {
        try {
            (new MemberModuleProvider())->identityCommands()->assertMobileBound($context, (string)$params['mobile']);
            $result = (new ModuleProvider())->verification()->verifyCode(
                $context,
                NoticeSceneEnum::RESET_PASSWORD,
                (string) $params['mobile'],
                (string) $params['code']
            );
            if (!$result->accepted) {
                throw new \RuntimeException($result->error);
            }

            (new MemberModuleProvider())->identityCommands()->resetPasswordByVerifiedMobile(
                $context,
                (string)$params['mobile'],
                (string)$params['password'],
            );
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    private static function loginResult(Member $member, bool $recordLogin = true): array
    {
        if ($recordLogin) {
            throw new \LogicException('Member login must be recorded by MemberIdentityCommands');
        }

        return [
            'token'    => UserTokenService::createToken((int) $member->id),
            'id'       => $member->id,
            'sn'       => $member->sn,
            'nickname' => $member->nickname,
            'avatar'   => FileService::getFileUrl((string) $member->avatar),
            'mobile'   => $member->mobile,
        ];
    }

    private static function assertLoginWayEnabled(
        TenantContext|TenantSystemContext $context,
        int $way,
    ): void
    {
        $enabled = TenantApplicationSettingService::login($context)['login_way'];
        if (!in_array($way, $enabled, true)) {
            throw new \RuntimeException('当前登录方式未启用');
        }
    }

    private static function defaultAvatar(TenantContext|TenantSystemContext $context): string
    {
        $avatar = trim((string)TenantApplicationSettingService::memberProfile($context)['user_avatar']);
        return $avatar !== '' ? $avatar : (string)config('project.default_image.user_avatar', '');
    }

    /** 退出（JWT 无状态，客户端丢弃 token 即可） */
    public static function logout(): bool
    {
        return true;
    }
}
