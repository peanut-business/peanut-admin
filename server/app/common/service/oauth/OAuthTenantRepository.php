<?php
declare(strict_types=1);

namespace app\common\service\oauth;

use app\Modules\Official\Oauth\Model\OAuthAttempt;
use app\Modules\Official\Oauth\Model\OAuthCompletionTicket;
use app\Modules\Official\Oauth\Model\OAuthIdentity;
use app\Modules\Official\Oauth\Model\OAuthPrincipal;
use app\Modules\Official\Member\ModuleProvider as MemberModuleProvider;
use app\common\service\member\AuthenticatedMemberContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

final class OAuthTenantRepository
{
    public static function principals(AuthenticatedMemberContext|TenantContext|TenantSystemContext $context)
    {
        OAuthTenantContext::tenantId($context);
        return OAuthPrincipal::where([]);
    }

    public static function identities(AuthenticatedMemberContext|TenantContext|TenantSystemContext $context)
    {
        OAuthTenantContext::tenantId($context);
        return OAuthIdentity::where([]);
    }

    public static function attempts(TenantContext|TenantSystemContext $context)
    {
        OAuthTenantContext::tenantId($context);
        return OAuthAttempt::where([]);
    }

    public static function completionTickets(TenantContext|TenantSystemContext $context)
    {
        OAuthTenantContext::tenantId($context);
        return OAuthCompletionTicket::where([]);
    }

    public static function createPrincipal(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        array $data
    ): OAuthPrincipal {
        OAuthTenantContext::tenantId($context);
        unset($data['tenant_id']);
        return OAuthPrincipal::create($data);
    }

    public static function createIdentity(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        array $data
    ): OAuthIdentity {
        OAuthTenantContext::tenantId($context);
        unset($data['tenant_id']);
        return OAuthIdentity::create($data);
    }

    public static function createAttempt(
        TenantContext|TenantSystemContext $context,
        array $data
    ): OAuthAttempt {
        OAuthTenantContext::tenantId($context);
        unset($data['tenant_id']);
        return OAuthAttempt::create($data);
    }

    public static function createCompletionTicket(
        TenantContext|TenantSystemContext $context,
        array $data
    ): OAuthCompletionTicket {
        OAuthTenantContext::tenantId($context);
        unset($data['tenant_id']);
        return OAuthCompletionTicket::create($data);
    }

    public static function subjectForOwnedMember(
        AuthenticatedMemberContext|TenantContext $context,
        int $memberId,
        int $terminal
    ): string
    {
        if ((new MemberModuleProvider())->queries()->identity($context, $memberId) === null) {
            return '';
        }
        return (string)self::identities($context)->where([
            'provider' => 'wechat',
            'member_id' => $memberId,
            'terminal' => $terminal,
        ])->value('subject');
    }
}
