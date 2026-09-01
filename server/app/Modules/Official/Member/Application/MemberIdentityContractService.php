<?php
declare(strict_types=1);

namespace app\Modules\Official\Member\Application;

use app\Modules\Official\Member\Contracts\Dto\MemberIdentitySnapshot;
use app\Modules\Official\Member\Contracts\MemberIdentityCommands;
use app\common\service\member\AuthenticatedMemberContext;
use app\Modules\Official\Member\Infrastructure\Persistence\MemberTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

final class MemberIdentityContractService implements MemberIdentityCommands
{
    public function register(TenantSystemContext $context, string $account, string $password, string $avatar): void
    {
        if (MemberTenantRepository::members($context)->where('account', $account)->count() > 0) {
            throw new \RuntimeException('账号已被注册');
        }
        $sn = MemberTenantRepository::nextMemberSn($context);
        MemberTenantRepository::createMember($context, [
            'sn' => $sn,
            'account' => $account,
            'password' => $this->passwordHashWithTimeSalt($password),
            'nickname' => '用户' . substr($sn, -6),
            'avatar' => $avatar,
            'status' => 1,
        ]);
    }

    public function login(TenantSystemContext $context, string $identifier, string $password, string $loginIp): MemberIdentitySnapshot
    {
        $member = MemberTenantRepository::members($context)->where(function ($query) use ($identifier): void {
            $query->where('account', $identifier)->whereOr('mobile', $identifier);
        })->findOrEmpty();
        if ($member->isEmpty()) {
            throw new \RuntimeException('账号不存在');
        }
        if (!(int)$member->status) {
            throw new \RuntimeException('账号已被禁用');
        }
        if (!$this->passwordMatches((string)$member->password, $password)) {
            throw new \RuntimeException('密码错误');
        }
        $member->login_time = time();
        $member->login_ip = $loginIp;
        $member->save();
        return self::snapshot($member);
    }

    public function loginByVerifiedMobile(
        TenantContext|TenantSystemContext $context,
        string $mobile,
        string $avatar,
        string $loginIp,
    ): MemberIdentitySnapshot {
        $member = MemberTenantRepository::members($context)->where('mobile', $mobile)->findOrEmpty();
        if ($member->isEmpty()) {
            $sn = MemberTenantRepository::nextMemberSn($context);
            $member = MemberTenantRepository::createMember($context, [
                'sn' => $sn,
                'account' => $mobile,
                'password' => '',
                'mobile' => $mobile,
                'nickname' => '用户' . substr($sn, -6),
                'avatar' => $avatar,
                'status' => 1,
            ]);
        }
        if (!(int)$member->status) {
            throw new \RuntimeException('账号已被禁用');
        }
        $member->login_time = time();
        $member->login_ip = $loginIp;
        $member->save();
        return self::snapshot($member);
    }

    public function resetPasswordByVerifiedMobile(
        TenantContext|TenantSystemContext $context,
        string $mobile,
        string $password,
    ): void {
        $member = MemberTenantRepository::members($context)->where('mobile', $mobile)->findOrEmpty();
        if ($member->isEmpty()) {
            throw new \RuntimeException('手机号未绑定账号');
        }
        $member->password = $this->passwordHashWithRandomSalt($password);
        $member->save();
    }

    public function assertMobileBound(TenantContext|TenantSystemContext $context, string $mobile): void
    {
        if (MemberTenantRepository::members($context)->where('mobile', $mobile)->findOrEmpty()->isEmpty()) {
            throw new \RuntimeException('手机号未绑定账号');
        }
    }

    public function assertMobileAvailable(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        int $memberId,
        string $mobile,
    ): void {
        if (!MemberTenantRepository::members($context)->where('mobile', $mobile)
            ->where('id', '<>', $memberId)->lock(true)->findOrEmpty()->isEmpty()) {
            throw new \RuntimeException('手机号已被其他账号绑定');
        }
    }

    public function changePassword(AuthenticatedMemberContext $context, int $memberId, string $oldPassword, string $newPassword): void
    {
        $member = MemberTenantRepository::members($context)->where('id', $memberId)->findOrEmpty();
        if ($member->isEmpty()) {
            throw new \RuntimeException('用户不存在');
        }
        if (!$this->passwordMatches((string)$member->password, $oldPassword)) {
            throw new \RuntimeException('原密码错误');
        }
        $member->password = $this->passwordHashWithTimeSalt($newPassword);
        $member->save();
    }

    public function bindVerifiedMobile(AuthenticatedMemberContext|TenantContext|TenantSystemContext $context, int $memberId, string $mobile): void
    {
        $this->assertMobileAvailable($context, $memberId, $mobile);
        if (MemberTenantRepository::members($context)->where('id', $memberId)->update(['mobile' => $mobile]) !== 1) {
            throw new \RuntimeException('用户不存在');
        }
    }

    public function createOAuthMember(TenantContext|TenantSystemContext $context, array $profile): MemberIdentitySnapshot
    {
        $sn = MemberTenantRepository::nextMemberSn($context);
        do {
            $account = 'wx_' . strtolower(bin2hex(random_bytes(6)));
        } while (MemberTenantRepository::members($context)->withTrashed()->where('account', $account)->count() > 0);
        $member = MemberTenantRepository::createMember($context, [
            'sn' => $sn,
            'account' => $account,
            'password' => '',
            'nickname' => mb_substr(
                (string)$profile['nickname'] !== '' ? (string)$profile['nickname'] : ('微信用户' . substr($sn, -6)),
                0,
                50,
            ),
            'avatar' => (string)$profile['avatar'],
            'mobile' => '',
            'channel' => (int)$profile['channel'],
            'is_new_user' => 1,
            'status' => 1,
        ]);
        return self::snapshot($member);
    }

    public function recordLogin(TenantContext|TenantSystemContext $context, int $memberId, string $loginIp): void
    {
        if (MemberTenantRepository::members($context)->where('id', $memberId)->update([
            'login_time' => time(),
            'login_ip' => $loginIp,
        ]) !== 1) {
            throw new \RuntimeException('用户不存在');
        }
    }

    private function passwordMatches(string $stored, string $password): bool
    {
        [$hash, $salt] = array_pad(explode(':', $stored, 2), 2, '');
        return md5(md5($password) . $salt) === $hash;
    }

    private function passwordHashWithTimeSalt(string $password): string
    {
        $salt = substr(md5((string)time()), 0, 8);
        return md5(md5($password) . $salt) . ':' . $salt;
    }

    private function passwordHashWithRandomSalt(string $password): string
    {
        $salt = substr(md5(uniqid((string)mt_rand(), true)), 0, 8);
        return md5(md5($password) . $salt) . ':' . $salt;
    }

    private static function snapshot(object $member): MemberIdentitySnapshot
    {
        return new MemberIdentitySnapshot(
            (int)$member->id,
            (string)$member->sn,
            (string)$member->nickname,
            (string)$member->avatar,
            (string)$member->mobile,
            (int)$member->status,
        );
    }
}
