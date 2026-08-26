<?php
declare(strict_types=1);

namespace app\common\service\oauth;

use app\Modules\Official\Oauth\Model\OAuthAttempt;
use app\Modules\Official\Oauth\Model\OAuthCompletionTicket;
use app\Modules\Official\Oauth\Model\OAuthIdentity;
use app\Modules\Official\Oauth\Model\OAuthPrincipal;
use app\common\service\member\MemberTenantRepository;
use app\common\service\member\AuthenticatedMemberContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

final class OAuthTenantRepository
{
    public static function principals(AuthenticatedMemberContext|TenantContext|TenantSystemContext $context)
    {
        return OAuthPrincipal::where('tenant_id', OAuthTenantContext::tenantId($context));
    }

    public static function identities(AuthenticatedMemberContext|TenantContext|TenantSystemContext $context)
    {
        return OAuthIdentity::where('tenant_id', OAuthTenantContext::tenantId($context));
    }

    public static function attempts(TenantContext|TenantSystemContext $context)
    {
        return OAuthAttempt::where('tenant_id', OAuthTenantContext::tenantId($context));
    }

    public static function completionTickets(TenantContext|TenantSystemContext $context)
    {
        return OAuthCompletionTicket::where('tenant_id', OAuthTenantContext::tenantId($context));
    }

    public static function createPrincipal(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        array $data
    ): OAuthPrincipal {
        unset($data['tenant_id']);
        return OAuthPrincipal::create([
            'tenant_id' => OAuthTenantContext::tenantId($context),
        ] + $data);
    }

    public static function createIdentity(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        array $data
    ): OAuthIdentity {
        unset($data['tenant_id']);
        return OAuthIdentity::create([
            'tenant_id' => OAuthTenantContext::tenantId($context),
        ] + $data);
    }

    public static function createAttempt(
        TenantContext|TenantSystemContext $context,
        array $data
    ): OAuthAttempt {
        unset($data['tenant_id']);
        return OAuthAttempt::create([
            'tenant_id' => OAuthTenantContext::tenantId($context),
        ] + $data);
    }

    public static function createCompletionTicket(
        TenantContext|TenantSystemContext $context,
        array $data
    ): OAuthCompletionTicket {
        unset($data['tenant_id']);
        return OAuthCompletionTicket::create([
            'tenant_id' => OAuthTenantContext::tenantId($context),
        ] + $data);
    }

    public static function subjectForOwnedMember(
        AuthenticatedMemberContext|TenantContext $context,
        int $memberId,
        int $terminal
    ): string
    {
        if (MemberTenantRepository::members($context)->where('id', $memberId)->count() !== 1) {
            return '';
        }
        return (string)self::identities($context)->where([
            'provider' => 'wechat',
            'member_id' => $memberId,
            'terminal' => $terminal,
        ])->value('subject');
    }
}
