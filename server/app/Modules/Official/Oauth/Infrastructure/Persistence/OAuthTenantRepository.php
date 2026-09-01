<?php
declare(strict_types=1);

namespace app\Modules\Official\Oauth\Infrastructure\Persistence;

use app\Modules\Official\Oauth\Model\OAuthAttempt;
use app\Modules\Official\Oauth\Model\OAuthCompletionTicket;
use app\Modules\Official\Oauth\Model\OAuthIdentity;
use app\Modules\Official\Oauth\Model\OAuthPrincipal;
use app\common\service\member\AuthenticatedMemberContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

final class OAuthTenantRepository
{
    public static function principals(AuthenticatedMemberContext|TenantContext|TenantSystemContext $context)
    {
        return OAuthPrincipal::where([]);
    }

    public static function identities(AuthenticatedMemberContext|TenantContext|TenantSystemContext $context)
    {
        return OAuthIdentity::where([]);
    }

    public static function attempts(TenantContext|TenantSystemContext $context)
    {
        return OAuthAttempt::where([]);
    }

    public static function completionTickets(TenantContext|TenantSystemContext $context)
    {
        return OAuthCompletionTicket::where([]);
    }

    public static function createPrincipal(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        array $data
    ): OAuthPrincipal {
        unset($data['tenant_id']);
        return OAuthPrincipal::create($data);
    }

    public static function createIdentity(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        array $data
    ): OAuthIdentity {
        unset($data['tenant_id']);
        return OAuthIdentity::create($data);
    }

    public static function createAttempt(
        TenantContext|TenantSystemContext $context,
        array $data
    ): OAuthAttempt {
        unset($data['tenant_id']);
        return OAuthAttempt::create($data);
    }

    public static function createCompletionTicket(
        TenantContext|TenantSystemContext $context,
        array $data
    ): OAuthCompletionTicket {
        unset($data['tenant_id']);
        return OAuthCompletionTicket::create($data);
    }
}
