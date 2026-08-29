<?php
declare(strict_types=1);

namespace app\Modules\Official\Oauth\Model;

use app\common\model\TenantOwnedModel;
use app\common\service\oauth\OAuthTenantRepository;
use app\common\service\member\AuthenticatedMemberContext;
use PeanutAdmin\Kernel\Auth\TenantContext;

/** 按 provider + client + subject 隔离的外部身份。 */
class OAuthIdentity extends TenantOwnedModel
{
    protected $name = 'oauth_identity';

    public static function subjectForMember(
        AuthenticatedMemberContext|TenantContext $context,
        int $memberId,
        int $terminal
    ): string
    {
        return OAuthTenantRepository::subjectForOwnedMember($context, $memberId, $terminal);
    }
}
