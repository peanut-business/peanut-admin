<?php
declare(strict_types=1);

namespace app\common\model\oauth;

use app\common\model\BaseModel;
use app\common\service\oauth\OAuthTenantRepository;

/** 按 provider + client + subject 隔离的外部身份。 */
class OAuthIdentity extends BaseModel
{
    protected $name = 'oauth_identity';

    public static function subjectForMember(int $memberId, int $terminal): string
    {
        return OAuthTenantRepository::subjectForOwnedMember($memberId, $terminal);
    }
}
