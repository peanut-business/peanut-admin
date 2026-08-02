<?php
declare(strict_types=1);

namespace app\common\model\oauth;

use app\common\model\BaseModel;

/** 浏览器 OAuth state 的一次性服务端记录，仅保存 state 哈希。 */
class OAuthAttempt extends BaseModel
{
    protected $name = 'oauth_attempt';
}
