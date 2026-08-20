<?php
declare(strict_types=1);

namespace app\api\logic;

use app\api\service\UserTokenService;
use app\common\logic\BaseLogic;
use app\common\enum\notice\NoticeSceneEnum;
use app\common\model\member\Member;
use app\common\service\FileService;
use app\common\service\config\TenantApplicationSettingService;
use app\common\service\member\MemberTenantRepository;
use app\common\service\notice\VerificationCodeService;
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
            if (MemberTenantRepository::members($context)->where('account', $params['account'])->count()) {
                throw new \Exception('账号已被注册');
            }

            $salt     = substr(md5((string) time()), 0, 8);
            $password = md5(md5($params['password']) . $salt);
            $sn       = Member::generateSn($context);

            MemberTenantRepository::createMember($context, [
                'sn'       => $sn,
                'account'  => $params['account'],
                'password' => $password . ':' . $salt,  // 存 hash:salt
                'nickname' => '用户' . substr($sn, -6),
                'avatar'   => self::defaultAvatar($context),
                'status'   => 1,
            ]);

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
            /** @var Member|null $member */
            $member = MemberTenantRepository::members($context)->where(function ($q) use ($params) {
                $q->where('account', $params['account'])
                  ->whereOr('mobile', $params['account']);
            })->find();

            if (!$member) {
                throw new \Exception('账号不存在');
            }
            if (!$member->status) {
                throw new \Exception('账号已被禁用');
            }

            // 验证密码
            [$hash, $salt] = array_pad(explode(':', (string) $member->password, 2), 2, '');
            if (md5(md5($params['password']) . $salt) !== $hash) {
                throw new \Exception('密码错误');
            }

            // 更新登录信息
            $member->login_time = time();
            $member->login_ip   = request()->ip();
            $member->save();

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
            $service = new VerificationCodeService();
            if (!$service->verify($context, NoticeSceneEnum::LOGIN_CODE, $mobile, (string) $params['code'])) {
                throw new \RuntimeException($service->getError());
            }

            $member = MemberTenantRepository::members($context)->where('mobile', $mobile)->findOrEmpty();
            if ($member->isEmpty()) {
                $sn = Member::generateSn($context);
                $member = MemberTenantRepository::createMember($context, [
                    'sn'       => $sn,
                    'account'  => $mobile,
                    'password' => '',
                    'mobile'   => $mobile,
                    'nickname' => '用户' . substr($sn, -6),
                    'avatar'   => self::defaultAvatar($context),
                    'status'   => 1,
                ]);
            }
            if (!(int) $member->status) {
                throw new \RuntimeException('账号已被禁用');
            }

            return self::loginResult($member);
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function resetPassword(TenantContext|TenantSystemContext $context, array $params): bool
    {
        try {
            $member = MemberTenantRepository::members($context)->where('mobile', (string) $params['mobile'])->findOrEmpty();
            if ($member->isEmpty()) {
                throw new \RuntimeException('手机号未绑定账号');
            }

            $service = new VerificationCodeService();
            if (!$service->verify(
                $context,
                NoticeSceneEnum::RESET_PASSWORD,
                (string) $params['mobile'],
                (string) $params['code']
            )) {
                throw new \RuntimeException($service->getError());
            }

            [$hash, $salt] = self::passwordHash((string) $params['password']);
            $member->password = $hash . ':' . $salt;
            $member->save();
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    private static function loginResult(Member $member): array
    {
        $member->login_time = time();
        $member->login_ip = request()->ip();
        $member->save();

        return [
            'token'    => UserTokenService::createToken((int) $member->id),
            'id'       => $member->id,
            'sn'       => $member->sn,
            'nickname' => $member->nickname,
            'avatar'   => FileService::getFileUrl((string) $member->avatar),
            'mobile'   => $member->mobile,
        ];
    }

    /** @return array{0:string,1:string} */
    private static function passwordHash(string $password): array
    {
        $salt = substr(md5(uniqid((string) mt_rand(), true)), 0, 8);
        return [md5(md5($password) . $salt), $salt];
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
