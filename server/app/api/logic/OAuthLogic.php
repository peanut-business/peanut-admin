<?php
declare(strict_types=1);

namespace app\api\logic;

use app\api\service\UserTokenService;
use app\common\enum\notice\NoticeSceneEnum;
use app\common\logic\BaseLogic;
use app\common\model\member\Member;
use app\common\service\ConfigService;
use app\common\service\FileService;
use app\common\service\notice\VerificationCodeService;
use app\common\service\oauth\OAuthTenantContext;
use app\common\service\oauth\OAuthTenantRepository;
use app\common\service\oauth\WechatOAuthTransport;
use app\common\service\oauth\contract\OAuthTransportInterface;
use app\common\service\oauth\dto\OAuthProfile;
use app\common\service\member\MemberTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;
use think\facade\Db;

/** 微信 OAuth 登录、身份归一、绑定与受限首登补全。 */
class OAuthLogic extends BaseLogic
{
    private const PROVIDER = 'wechat';
    private const UNION_SCOPE = 'wechat_default';
    private const ATTEMPT_TTL = 600;
    private const COMPLETION_TTL = 600;
    private const SCENES = [
        'mnp' => ['config' => 'mnp_setting', 'terminal' => 1],
        'oa' => ['config' => 'oa_setting', 'terminal' => 2],
        'open_pc' => ['config' => 'open_platform', 'terminal' => 4],
    ];

    public static function begin(
        TenantSystemContext $context,
        string $scene,
        string $returnPath,
        string $redirectUri,
        ?OAuthTransportInterface $transport = null
    ): array|false {
        try {
            if (!in_array($scene, ['oa', 'open_pc'], true)) {
                throw new \RuntimeException('该微信场景不支持浏览器授权');
            }
            self::assertReturnPath($returnPath);
            $config = self::sceneConfig($scene);
            $state = bin2hex(random_bytes(32));
            OAuthTenantRepository::createAttempt($context, [
                'state_hash' => hash('sha256', $state),
                'scene' => $scene,
                'return_path' => $returnPath,
                'expires_at' => time() + self::ATTEMPT_TTL,
                'used_at' => null,
            ]);
            $transport ??= new WechatOAuthTransport();
            return [
                'authorization_url' => $transport->authorizationUrl(
                    $scene,
                    $config,
                    $redirectUri,
                    $state
                ),
                'expires_in' => self::ATTEMPT_TTL,
            ];
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function callback(
        TenantSystemContext $context,
        string $scene,
        string $code,
        string $state,
        ?OAuthTransportInterface $transport = null
    ): array|false {
        try {
            if (!in_array($scene, ['oa', 'open_pc'], true)) {
                throw new \RuntimeException('微信授权场景无效');
            }
            $returnPath = self::consumeAttempt($context, $scene, $state);
            $transport ??= new WechatOAuthTransport();
            $profile = $transport->exchange($scene, self::sceneConfig($scene), $code);
            $result = self::loginWithProfile($context, $scene, $profile);
            $result['return_path'] = $returnPath;
            return $result;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function miniProgramLogin(
        TenantSystemContext $context,
        string $code,
        ?OAuthTransportInterface $transport = null
    ): array|false {
        try {
            $transport ??= new WechatOAuthTransport();
            $profile = $transport->exchange('mnp', self::sceneConfig('mnp'), $code);
            return self::loginWithProfile($context, 'mnp', $profile);
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function bind(
        TenantContext $context,
        int $memberId,
        string $scene,
        string $code,
        ?OAuthTransportInterface $transport = null
    ): bool {
        try {
            if (!in_array($scene, ['mnp', 'oa'], true)) {
                throw new \RuntimeException('该微信场景不支持账号绑定');
            }
            $member = MemberTenantRepository::members($context)->where('id', $memberId)->findOrEmpty();
            if ($member->isEmpty() || !(int)$member->status) {
                throw new \RuntimeException('用户不存在或已禁用');
            }
            $transport ??= new WechatOAuthTransport();
            $profile = $transport->exchange($scene, self::sceneConfig($scene), $code);
            [$bound] = self::resolveIdentity($context, $scene, $profile, $memberId);
            if ((int)$bound->id !== $memberId) {
                throw new \RuntimeException('微信身份已绑定其他用户');
            }
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function complete(TenantContext|TenantSystemContext $context, array $params): array|false
    {
        $rawTicket = trim((string)($params['ticket'] ?? ''));
        if ($rawTicket === '') {
            self::setError('登录补全票据缺失');
            return false;
        }

        Db::startTrans();
        try {
            $ticket = OAuthTenantRepository::completionTickets($context)
                ->where('token_hash', hash('sha256', $rawTicket))
                ->lock(true)->findOrEmpty();
            if ($ticket->isEmpty() || !empty($ticket->used_at) || (int)$ticket->expires_at < time()) {
                throw new \RuntimeException('登录补全票据无效或已过期');
            }
            /** @var Member $member */
            $member = MemberTenantRepository::members($context)->lock(true)->findOrEmpty((int)$ticket->member_id);
            if ($member->isEmpty() || !(int)$member->status) {
                throw new \RuntimeException('用户不存在或已禁用');
            }

            if ((int)$ticket->need_profile === 1) {
                $nickname = trim((string)($params['nickname'] ?? ''));
                if ($nickname === '' || mb_strlen($nickname) > 50) {
                    throw new \RuntimeException('请填写有效昵称');
                }
                $member->nickname = $nickname;
                if (trim((string)($params['avatar'] ?? '')) !== '') {
                    $member->avatar = FileService::setFileUrl((string)$params['avatar']);
                }
            }

            if ((int)$ticket->need_mobile === 1) {
                $mobile = trim((string)($params['mobile'] ?? ''));
                if (!preg_match('/^1[3-9]\d{9}$/', $mobile)) {
                    throw new \RuntimeException('手机号格式错误');
                }
                $occupied = MemberTenantRepository::members($context)->where('mobile', $mobile)
                    ->where('id', '<>', (int)$member->id)->lock(true)->findOrEmpty();
                if (!$occupied->isEmpty()) {
                    throw new \RuntimeException('手机号已被其他账号绑定');
                }
                $verification = new VerificationCodeService();
                if (!$verification->verify(
                    $context,
                    NoticeSceneEnum::BIND_MOBILE,
                    $mobile,
                    (string)($params['code'] ?? '')
                )) {
                    throw new \RuntimeException($verification->getError());
                }
                $member->mobile = $mobile;
            }

            $member->is_new_user = 0;
            $member->login_time = time();
            $member->login_ip = request()->ip();
            $member->save();
            $ticket->used_at = time();
            $ticket->save();
            Db::commit();
            return self::fullLoginResult($member);
        } catch (\Throwable $e) {
            Db::rollback();
            self::setError($e->getMessage());
            return false;
        }
    }

    private static function loginWithProfile(TenantSystemContext $context, string $scene, OAuthProfile $profile): array
    {
        [$member, $created] = self::resolveIdentity($context, $scene, $profile, null);
        if (!(int)$member->status) {
            throw new \RuntimeException('账号已被禁用');
        }
        $needProfile = $created && $scene === 'mnp';
        $needMobile = (int)ConfigService::get('login', 'coerce_mobile', 0) === 1
            && trim((string)$member->mobile) === '';
        if ($needProfile || $needMobile) {
            return self::completionResult($context, $member, $needProfile, $needMobile);
        }
        $member->login_time = time();
        $member->login_ip = request()->ip();
        $member->save();
        return self::fullLoginResult($member);
    }

    /** @return array{0:Member,1:bool} */
    private static function resolveIdentity(
        TenantContext|TenantSystemContext $context,
        string $scene,
        OAuthProfile $profile,
        ?int $bindingMemberId
    ): array {
        $sceneMeta = self::SCENES[$scene];
        $config = self::sceneConfig($scene);
        $clientKey = $scene . ':' . (string)$config['app_id'];
        $tenantId = OAuthTenantContext::tenantId($context);
        $lockSeed = $profile->unionId() !== ''
            ? 'union:' . $profile->unionId()
            : 'identity:' . $clientKey . ':' . $profile->subject();
        $lockName = 'peanut:oauth:' . substr(hash('sha256', $tenantId . ':' . $lockSeed), 0, 48);
        $lockRows = Db::query('SELECT GET_LOCK(:name, 5) AS acquired', ['name' => $lockName]);
        if ((int)($lockRows[0]['acquired'] ?? 0) !== 1) {
            throw new \RuntimeException('微信登录正在处理中，请稍后重试');
        }

        try {
            Db::startTrans();
            try {
                $identity = OAuthTenantRepository::identities($context)->where([
                    'provider' => self::PROVIDER,
                    'client_key' => $clientKey,
                    'subject' => $profile->subject(),
                ])->lock(true)->findOrEmpty();
                if (!$identity->isEmpty()) {
                    if ($bindingMemberId !== null && (int)$identity->member_id !== $bindingMemberId) {
                        throw new \RuntimeException('微信身份已绑定其他用户');
                    }
                    $member = MemberTenantRepository::members($context)->lock(true)->findOrEmpty((int)$identity->member_id);
                    if ($member->isEmpty()) {
                        throw new \RuntimeException('微信身份关联用户不存在');
                    }
                    $principalId = self::assertPrincipalOwnership($context, $profile, (int)$member->id);
                    if ($principalId !== null && (int)$identity->principal_id !== $principalId) {
                        $identity->principal_id = $principalId;
                        $identity->save();
                    }
                    self::updateProfile($member, $profile);
                    Db::commit();
                    return [$member, false];
                }

                $principal = null;
                if ($profile->unionId() !== '') {
                    $principal = OAuthTenantRepository::principals($context)->where([
                        'provider' => self::PROVIDER,
                        'union_scope' => self::UNION_SCOPE,
                        'union_id' => $profile->unionId(),
                    ])->lock(true)->findOrEmpty();
                }

                $created = false;
                if ($bindingMemberId !== null) {
                    $member = MemberTenantRepository::members($context)->lock(true)->findOrEmpty($bindingMemberId);
                    if ($member->isEmpty()) {
                        throw new \RuntimeException('用户不存在');
                    }
                    if ($principal !== null && !$principal->isEmpty()
                        && (int)$principal->member_id !== $bindingMemberId) {
                        throw new \RuntimeException('微信联合身份已归属其他用户，不能自动合并账号');
                    }
                } elseif ($principal !== null && !$principal->isEmpty()) {
                    $member = MemberTenantRepository::members($context)->lock(true)->findOrEmpty((int)$principal->member_id);
                    if ($member->isEmpty()) {
                        throw new \RuntimeException('微信联合身份关联用户不存在');
                    }
                } else {
                    $member = self::createMember($context, $profile, (int)$sceneMeta['terminal']);
                    $created = true;
                }

                if ($profile->unionId() !== '' && ($principal === null || $principal->isEmpty())) {
                    $principal = OAuthTenantRepository::createPrincipal($context, [
                        'provider' => self::PROVIDER,
                        'union_scope' => self::UNION_SCOPE,
                        'union_id' => $profile->unionId(),
                        'member_id' => (int)$member->id,
                    ]);
                }
                $sameClient = OAuthTenantRepository::identities($context)->where([
                    'provider' => self::PROVIDER,
                    'client_key' => $clientKey,
                    'member_id' => (int)$member->id,
                ])->lock(true)->findOrEmpty();
                if (!$sameClient->isEmpty()) {
                    throw new \RuntimeException('当前用户已绑定该微信应用的其他身份');
                }
                OAuthTenantRepository::createIdentity($context, [
                    'provider' => self::PROVIDER,
                    'client_key' => $clientKey,
                    'subject' => $profile->subject(),
                    'principal_id' => $principal !== null && !$principal->isEmpty()
                        ? (int)$principal->id
                        : null,
                    'member_id' => (int)$member->id,
                    'terminal' => (int)$sceneMeta['terminal'],
                ]);
                self::updateProfile($member, $profile);
                Db::commit();
                return [$member, $created];
            } catch (\Throwable $e) {
                Db::rollback();
                throw $e;
            }
        } finally {
            try {
                Db::query('SELECT RELEASE_LOCK(:name)', ['name' => $lockName]);
            } catch (\Throwable) {
            }
        }
    }

    private static function assertPrincipalOwnership(
        TenantContext|TenantSystemContext $context,
        OAuthProfile $profile,
        int $memberId
    ): ?int
    {
        if ($profile->unionId() === '') {
            return null;
        }
        $principal = OAuthTenantRepository::principals($context)->where([
            'provider' => self::PROVIDER,
            'union_scope' => self::UNION_SCOPE,
            'union_id' => $profile->unionId(),
        ])->lock(true)->findOrEmpty();
        if (!$principal->isEmpty() && (int)$principal->member_id !== $memberId) {
            throw new \RuntimeException('微信联合身份归属冲突，不能自动合并账号');
        }
        if ($principal->isEmpty()) {
            $principal = OAuthTenantRepository::createPrincipal($context, [
                'provider' => self::PROVIDER,
                'union_scope' => self::UNION_SCOPE,
                'union_id' => $profile->unionId(),
                'member_id' => $memberId,
            ]);
        }
        return (int)$principal->id;
    }

    private static function createMember(TenantContext|TenantSystemContext $context, OAuthProfile $profile, int $terminal): Member
    {
        $sn = Member::generateSn($context);
        do {
            $account = 'wx_' . strtolower(bin2hex(random_bytes(6)));
        } while (MemberTenantRepository::members($context)->withTrashed()->where('account', $account)->count() > 0);
        return MemberTenantRepository::createMember($context, [
            'sn' => $sn,
            'account' => $account,
            'password' => '',
            'nickname' => mb_substr($profile->nickname() ?: ('微信用户' . substr($sn, -6)), 0, 50),
            'avatar' => $profile->avatar() !== ''
                ? $profile->avatar()
                : (string)ConfigService::get('default_image', 'user_avatar', ''),
            'mobile' => '',
            'channel' => $terminal,
            'is_new_user' => 1,
            'status' => 1,
        ]);
    }

    private static function updateProfile(Member $member, OAuthProfile $profile): void
    {
        $changed = false;
        if ($profile->nickname() !== '' && trim((string)$member->nickname) === '') {
            $member->nickname = mb_substr($profile->nickname(), 0, 50);
            $changed = true;
        }
        if ($profile->avatar() !== '' && trim((string)$member->avatar) === '') {
            $member->avatar = $profile->avatar();
            $changed = true;
        }
        if ($changed) {
            $member->save();
        }
    }

    private static function completionResult(
        TenantContext|TenantSystemContext $context,
        Member $member,
        bool $needProfile,
        bool $needMobile
    ): array
    {
        $raw = bin2hex(random_bytes(32));
        OAuthTenantRepository::createCompletionTicket($context, [
            'token_hash' => hash('sha256', $raw),
            'member_id' => (int)$member->id,
            'need_profile' => $needProfile ? 1 : 0,
            'need_mobile' => $needMobile ? 1 : 0,
            'expires_at' => time() + self::COMPLETION_TTL,
            'used_at' => null,
        ]);
        return [
            'completed' => false,
            'completion_ticket' => $raw,
            'expires_in' => self::COMPLETION_TTL,
            'need_profile' => $needProfile,
            'need_mobile' => $needMobile,
            'member' => self::memberSummary($member),
        ];
    }

    private static function fullLoginResult(Member $member): array
    {
        return [
            'completed' => true,
            'token' => UserTokenService::createToken((int)$member->id),
            'member' => self::memberSummary($member),
        ];
    }

    private static function memberSummary(Member $member): array
    {
        return [
            'id' => (int)$member->id,
            'sn' => (string)$member->sn,
            'nickname' => (string)$member->nickname,
            'avatar' => FileService::getFileUrl((string)$member->avatar),
            'mobile' => (string)$member->mobile,
        ];
    }

    private static function consumeAttempt(
        TenantSystemContext $context,
        string $scene,
        string $state
    ): string
    {
        $state = trim($state);
        if ($state === '') {
            throw new \RuntimeException('微信授权 state 缺失');
        }
        Db::startTrans();
        try {
            $attempt = OAuthTenantRepository::attempts($context)
                ->where('state_hash', hash('sha256', $state))
                ->lock(true)->findOrEmpty();
            if ($attempt->isEmpty() || (string)$attempt->scene !== $scene
                || !empty($attempt->used_at) || (int)$attempt->expires_at < time()) {
                throw new \RuntimeException('微信授权 state 无效、已过期或已使用');
            }
            $attempt->used_at = time();
            $attempt->save();
            $path = (string)$attempt->return_path;
            Db::commit();
            return $path;
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    private static function sceneConfig(string $scene): array
    {
        self::assertEnabled();
        if (!isset(self::SCENES[$scene])) {
            throw new \RuntimeException('微信授权场景无效');
        }
        $type = (string)self::SCENES[$scene]['config'];
        $config = [
            'app_id' => trim((string)ConfigService::get($type, 'app_id', '')),
            'app_secret' => trim((string)ConfigService::get($type, 'app_secret', '')),
        ];
        if ($config['app_id'] === '' || $config['app_secret'] === '') {
            throw new \RuntimeException('微信授权渠道未配置');
        }
        return $config;
    }

    private static function assertEnabled(): void
    {
        if ((int)ConfigService::get('login', 'third_auth', 0) !== 1
            || (int)ConfigService::get('login', 'wechat_auth', 0) !== 1) {
            throw new \RuntimeException('微信授权登录未开启');
        }
    }

    private static function assertReturnPath(string $path): void
    {
        if ($path === '' || strlen($path) > 500 || !str_starts_with($path, '/')
            || str_starts_with($path, '//') || str_contains($path, '\\')) {
            throw new \RuntimeException('授权返回地址仅允许站内路径');
        }
        $parts = parse_url($path);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
            throw new \RuntimeException('授权返回地址仅允许站内路径');
        }
    }
}
