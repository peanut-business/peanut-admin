<?php
declare(strict_types=1);

namespace app\common\model\oauth;

use app\common\model\BaseModel;

/** 按 provider + client + subject 隔离的外部身份。 */
class OAuthIdentity extends BaseModel
{
    protected $name = 'oauth_identity';

    public static function subjectForMember(int $memberId, int $terminal): string
    {
        return (string)self::where([
            'provider' => 'wechat',
            'member_id' => $memberId,
            'terminal' => $terminal,
        ])->value('subject');
    }
}
