<?php
declare(strict_types=1);

namespace app\api\logic;

use app\api\service\UserTokenService;
use app\common\logic\BaseLogic;
use app\common\model\member\Member;
use app\common\service\FileService;

class LoginLogic extends BaseLogic
{
    /**
     * 账号注册
     * params: account, password, scene(默认h5)
     */
    public static function register(array $params): bool
    {
        try {
            if (Member::where('account', $params['account'])->count()) {
                throw new \Exception('账号已被注册');
            }

            $salt     = substr(md5((string) time()), 0, 8);
            $password = md5(md5($params['password']) . $salt);
            $sn       = Member::generateSn();

            Member::create([
                'sn'       => $sn,
                'account'  => $params['account'],
                'password' => $password . ':' . $salt,  // 存 hash:salt
                'nickname' => '用户' . substr($sn, -6),
                'avatar'   => '',
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
    public static function login(array $params): array|false
    {
        try {
            /** @var Member|null $member */
            $member = Member::where(function ($q) use ($params) {
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

    /** 退出（JWT 无状态，客户端丢弃 token 即可） */
    public static function logout(): bool
    {
        return true;
    }
}
