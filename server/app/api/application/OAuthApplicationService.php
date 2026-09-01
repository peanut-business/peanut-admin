<?php
declare(strict_types=1);

namespace app\api\application;

use app\Modules\Official\Notification\ModuleProvider;
use app\Modules\Official\Member\Contracts\Dto\MemberIdentitySnapshot;
use app\Modules\Official\Member\Contracts\MemberIdentityCommands;
use app\Modules\Official\Member\Contracts\MemberProfileCommands;
use app\Modules\Official\Member\Contracts\MemberQueries;
use app\api\service\UserTokenService;
use app\common\enum\notice\NoticeSceneEnum;
use app\common\application\ApplicationService;
use app\common\persistence\AdvisoryLockExecution;
use app\common\persistence\AdvisoryLockUnavailable;
use app\common\persistence\TransactionalExecution;
use app\common\service\config\TenantApplicationSettingService;
use app\common\service\oauth\OAuthTenantContext;
use app\common\service\oauth\OAuthTenantRepository;
use app\common\service\oauth\WechatOAuthTransport;
use app\common\service\oauth\contract\OAuthTransportInterface;
use app\common\service\oauth\dto\OAuthProfile;
use app\common\service\member\AuthenticatedMemberContext;
use app\common\service\external\ExternalTenantBinding;
use app\common\service\external\ExternalTenantResolver;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

/** 微信 OAuth 登录、身份归一、绑定与受限首登补全。 */
class OAuthApplicationService extends ApplicationService
{
    private const PROVIDER = 'wechat';
    private const UNION_SCOPE = 'wechat_default';
    private const ATTEMPT_TTL = 600;
    private const COMPLETION_TTL = 600;
    private const SCENES = [
        'mnp' => ['terminal' => 1],
        'oa' => ['terminal' => 2],
        'open_pc' => ['terminal' => 4],
    ];

    public function __construct(
        private readonly MemberQueries $members,
        private readonly MemberIdentityCommands $memberIdentities,
        private readonly MemberProfileCommands $memberProfiles,
    ) {
    }

    public function begin(
        TenantSystemContext $context,
        string $scene,
        string $returnPath,
        string $redirectUri,
        ExternalTenantBinding $binding,
        ?OAuthTransportInterface $transport = null
    ): array|false {
        try {
            if (!in_array($scene, ['oa', 'open_pc'], true)) {
                throw new \RuntimeException('该微信场景不支持浏览器授权');
            }
            self::assertReturnPath($returnPath);
            $config = $binding->config;
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

    public function callback(
        TenantSystemContext $context,
        string $scene,
        string $code,
        string $state,
        ExternalTenantBinding $binding,
        ?OAuthTransportInterface $transport = null
    ): array|false {
        try {
            if (!in_array($scene, ['oa', 'open_pc'], true)) {
                throw new \RuntimeException('微信授权场景无效');
            }
            $returnPath = self::consumeAttempt($context, $scene, $state);
            $transport ??= new WechatOAuthTransport();
            $profile = $transport->exchange($scene, $binding->config, $code);
            $result = $this->loginWithProfile($context, $scene, $profile, $binding);
            $result['return_path'] = $returnPath;
            return $result;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public function miniProgramLogin(
        TenantSystemContext $context,
        string $code,
        ExternalTenantBinding $binding,
        ?OAuthTransportInterface $transport = null
    ): array|false {
        try {
            $transport ??= new WechatOAuthTransport();
            $profile = $transport->exchange('mnp', $binding->config, $code);
            return $this->loginWithProfile($context, 'mnp', $profile, $binding);
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public function bind(
        AuthenticatedMemberContext $context,
        int $memberId,
        string $scene,
        string $code,
        ?OAuthTransportInterface $transport = null
    ): bool {
        try {
            if (!in_array($scene, ['mnp', 'oa'], true)) {
                throw new \RuntimeException('该微信场景不支持账号绑定');
            }
            $member = $this->members->identity($context, $memberId);
            if ($member === null || !$member->status) {
                throw new \RuntimeException('用户不存在或已禁用');
            }
            $transport ??= new WechatOAuthTransport();
            $binding = ExternalTenantResolver::production()->bindingForTenant(
                $context,
                ExternalTenantResolver::oauthProvider($scene),
            );
            $profile = $transport->exchange($scene, $binding->config, $code);
            [$bound] = $this->resolveIdentity($context, $scene, $profile, $memberId, $binding);
            if ((int)$bound->id !== $memberId) {
                throw new \RuntimeException('微信身份已绑定其他用户');
            }
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public function complete(TenantContext|TenantSystemContext $context, array $params): array|false
    {
        $rawTicket = trim((string)($params['ticket'] ?? ''));
        if ($rawTicket === '') {
            self::setError('登录补全票据缺失');
            return false;
        }

        try {
            return app(TransactionalExecution::class)->run(function () use ($context, $params, $rawTicket): array {
                $ticket = OAuthTenantRepository::completionTickets($context)
                    ->where('token_hash', hash('sha256', $rawTicket))
                    ->lock(true)->findOrEmpty();
                if ($ticket->isEmpty() || !empty($ticket->used_at) || (int)$ticket->expires_at < time()) {
                    throw new \RuntimeException('登录补全票据无效或已过期');
                }
                $member = $this->members->lockedIdentity(
                    $context,
                    (int)$ticket->member_id,
                );
                if ($member === null || !$member->status) {
                    throw new \RuntimeException('用户不存在或已禁用');
                }

                $nickname = null;
                $avatar = null;
                if ((int)$ticket->need_profile === 1) {
                    $nickname = trim((string)($params['nickname'] ?? ''));
                    if ($nickname === '' || mb_strlen($nickname) > 50) {
                        throw new \RuntimeException('请填写有效昵称');
                    }
                    if (trim((string)($params['avatar'] ?? '')) !== '') {
                        // Storage URL ownership remains outside OAuth; Member persists the opaque value.
                        $avatar = (string)$params['avatar'];
                    }
                }

                if ((int)$ticket->need_mobile === 1) {
                    $mobile = trim((string)($params['mobile'] ?? ''));
                    if (!preg_match('/^1[3-9]\d{9}$/', $mobile)) {
                        throw new \RuntimeException('手机号格式错误');
                    }
                    $this->memberIdentities->assertMobileAvailable(
                        $context,
                        $member->id,
                        $mobile,
                    );
                    $result = (new ModuleProvider())->verification()->verifyCode(
                        $context,
                        NoticeSceneEnum::BIND_MOBILE,
                        $mobile,
                        (string)($params['code'] ?? '')
                    );
                    if (!$result->accepted) {
                        throw new \RuntimeException($result->error);
                    }
                    $this->memberIdentities->bindVerifiedMobile(
                        $context,
                        $member->id,
                        $mobile,
                    );
                }

                $this->memberProfiles->completeOAuthProfile(
                    $context,
                    $member->id,
                    $nickname,
                    $avatar,
                    time(),
                    request()->ip(),
                );
                $member = $this->members->identity($context, $member->id);
                if ($member === null) {
                    throw new \RuntimeException('用户不存在');
                }
                $ticket->used_at = time();
                $ticket->save();
                return self::fullLoginResult($member);
            });
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    private function loginWithProfile(
        TenantSystemContext $context,
        string $scene,
        OAuthProfile $profile,
        ExternalTenantBinding $binding,
    ): array
    {
        [$member, $created] = $this->resolveIdentity($context, $scene, $profile, null, $binding);
        if (!$member->status) {
            throw new \RuntimeException('账号已被禁用');
        }
        $needProfile = $created && $scene === 'mnp';
        $needMobile = (int)TenantApplicationSettingService::login($context)['coerce_mobile'] === 1
            && trim($member->mobile) === '';
        if ($needProfile || $needMobile) {
            return self::completionResult($context, $member, $needProfile, $needMobile, $binding);
        }
        $this->memberIdentities->recordLogin($context, $member->id, request()->ip());
        return self::fullLoginResult($member);
    }

    /** @return array{0:MemberIdentitySnapshot,1:bool} */
    private function resolveIdentity(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        string $scene,
        OAuthProfile $profile,
        ?int $bindingMemberId,
        ExternalTenantBinding $binding,
    ): array {
        $sceneMeta = self::SCENES[$scene];
        $clientKey = $scene . ':' . (string)($binding->config['app_id'] ?? '');
        $tenantId = OAuthTenantContext::tenantId($context);
        $lockSeed = $profile->unionId() !== ''
            ? 'union:' . $profile->unionId()
            : 'identity:' . $clientKey . ':' . $profile->subject();
        $lockName = 'peanut:oauth:' . substr(hash('sha256', $tenantId . ':' . $lockSeed), 0, 48);
        try {
            return app(AdvisoryLockExecution::class)->run(
                $lockName,
                5,
                fn(): array => app(TransactionalExecution::class)->run(function () use (
                    $bindingMemberId,
                    $clientKey,
                    $context,
                    $profile,
                    $sceneMeta,
                ): array {
                    $identity = OAuthTenantRepository::identities($context)->where([
                        'provider' => self::PROVIDER,
                        'client_key' => $clientKey,
                        'subject' => $profile->subject(),
                    ])->lock(true)->findOrEmpty();
                    if (!$identity->isEmpty()) {
                        if ($bindingMemberId !== null && (int)$identity->member_id !== $bindingMemberId) {
                            throw new \RuntimeException('微信身份已绑定其他用户');
                        }
                        $member = $this->members->lockedIdentity(
                            $context,
                            (int)$identity->member_id,
                        );
                        if ($member === null) {
                            throw new \RuntimeException('微信身份关联用户不存在');
                        }
                        $principalId = $this->assertPrincipalOwnership($context, $profile, $member->id);
                        if ($principalId !== null && (int)$identity->principal_id !== $principalId) {
                            $identity->principal_id = $principalId;
                            $identity->save();
                        }
                        $member = $this->updateProfile($context, $member, $profile);
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
                        $member = $this->members->lockedIdentity($context, $bindingMemberId);
                        if ($member === null) {
                            throw new \RuntimeException('用户不存在');
                        }
                        if ($principal !== null && !$principal->isEmpty()
                            && (int)$principal->member_id !== $bindingMemberId) {
                            throw new \RuntimeException('微信联合身份已归属其他用户，不能自动合并账号');
                        }
                    } elseif ($principal !== null && !$principal->isEmpty()) {
                        $member = $this->members->lockedIdentity(
                            $context,
                            (int)$principal->member_id,
                        );
                        if ($member === null) {
                            throw new \RuntimeException('微信联合身份关联用户不存在');
                        }
                    } else {
                        if ($context instanceof AuthenticatedMemberContext) {
                            throw new \RuntimeException('已认证会员不能创建替代会员身份');
                        }
                        $member = $this->createMember($context, $profile, (int)$sceneMeta['terminal']);
                        $created = true;
                    }

                    if ($profile->unionId() !== '' && ($principal === null || $principal->isEmpty())) {
                        $principal = OAuthTenantRepository::createPrincipal($context, [
                            'provider' => self::PROVIDER,
                            'union_scope' => self::UNION_SCOPE,
                            'union_id' => $profile->unionId(),
                            'member_id' => $member->id,
                        ]);
                    }
                    $sameClient = OAuthTenantRepository::identities($context)->where([
                        'provider' => self::PROVIDER,
                        'client_key' => $clientKey,
                        'member_id' => $member->id,
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
                        'member_id' => $member->id,
                        'terminal' => (int)$sceneMeta['terminal'],
                    ]);
                    $member = $this->updateProfile($context, $member, $profile);
                    return [$member, $created];
                }),
            );
        } catch (AdvisoryLockUnavailable) {
            throw new \RuntimeException('微信登录正在处理中，请稍后重试');
        }
    }

    private function assertPrincipalOwnership(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
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

    private function createMember(
        TenantContext|TenantSystemContext $context,
        OAuthProfile $profile,
        int $terminal
    ): MemberIdentitySnapshot
    {
        return $this->memberIdentities->createOAuthMember($context, [
            'nickname' => $profile->nickname(),
            'avatar' => $profile->avatar() !== ''
                ? $profile->avatar()
                : self::defaultAvatar($context),
            'channel' => $terminal,
        ]);
    }

    private function updateProfile(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        MemberIdentitySnapshot $member,
        OAuthProfile $profile,
    ): MemberIdentitySnapshot
    {
        $this->memberProfiles->fillOAuthProfile(
            $context,
            $member->id,
            $profile->nickname(),
            $profile->avatar(),
        );
        $updated = $this->members->identity($context, $member->id);
        if ($updated === null) {
            throw new \RuntimeException('用户不存在');
        }
        return $updated;
    }

    private static function defaultAvatar(TenantContext|TenantSystemContext $context): string
    {
        $avatar = trim((string)TenantApplicationSettingService::memberProfile($context)['user_avatar']);
        return $avatar !== '' ? $avatar : (string)config('project.default_image.user_avatar', '');
    }

    private static function completionResult(
        TenantContext|TenantSystemContext $context,
        MemberIdentitySnapshot $member,
        bool $needProfile,
        bool $needMobile,
        ExternalTenantBinding $binding,
    ): array
    {
        $raw = bin2hex(random_bytes(32));
        OAuthTenantRepository::createCompletionTicket($context, [
            'token_hash' => hash('sha256', $raw),
            'binding_id' => $binding->id,
            'member_id' => $member->id,
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

    private static function fullLoginResult(MemberIdentitySnapshot $member): array
    {
        return [
            'completed' => true,
            'token' => UserTokenService::createToken($member->id),
            'member' => self::memberSummary($member),
        ];
    }

    private static function memberSummary(MemberIdentitySnapshot $member): array
    {
        return [
            'id' => $member->id,
            'sn' => $member->sn,
            'nickname' => $member->nickname,
            'avatar' => \app\common\service\FileService::getFileUrl($member->avatar),
            'mobile' => $member->mobile,
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
        return app(TransactionalExecution::class)->run(function () use ($context, $scene, $state): string {
            $attempt = OAuthTenantRepository::attempts($context)
                ->where('state_hash', hash('sha256', $state))
                ->lock(true)->findOrEmpty();
            if ($attempt->isEmpty() || (string)$attempt->scene !== $scene
                || !empty($attempt->used_at) || (int)$attempt->expires_at < time()) {
                throw new \RuntimeException('微信授权 state 无效、已过期或已使用');
            }
            $attempt->used_at = time();
            $attempt->save();
            return (string)$attempt->return_path;
        });
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
