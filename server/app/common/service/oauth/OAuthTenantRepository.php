<?php
declare(strict_types=1);

namespace app\common\service\oauth;

use app\common\model\member\Member;
use app\common\model\oauth\OAuthAttempt;
use app\common\model\oauth\OAuthCompletionTicket;
use app\common\model\oauth\OAuthIdentity;
use app\common\model\oauth\OAuthPrincipal;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

final class OAuthTenantRepository
{
    public static function principals(TenantContext|TenantSystemContext $context)
    {
        return OAuthPrincipal::where('tenant_id', OAuthTenantContext::tenantId($context));
    }

    public static function identities(TenantContext|TenantSystemContext $context)
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
        TenantContext|TenantSystemContext $context,
        array $data
    ): OAuthPrincipal {
        unset($data['tenant_id']);
        return OAuthPrincipal::create([
            'tenant_id' => OAuthTenantContext::tenantId($context),
        ] + $data);
    }

    public static function createIdentity(
        TenantContext|TenantSystemContext $context,
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

    public static function subjectForOwnedMember(int $memberId, int $terminal): string
    {
        $tenantId = (int)Member::where('id', $memberId)->value('tenant_id');
        if ($tenantId < 1) {
            return '';
        }
        return (string)OAuthIdentity::where([
            'tenant_id' => $tenantId,
            'provider' => 'wechat',
            'member_id' => $memberId,
            'terminal' => $terminal,
        ])->value('subject');
    }
}
