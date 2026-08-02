<?php
declare(strict_types=1);

namespace app\common\model\oauth;

use app\common\model\BaseModel;

/** OAuth 首登资料/手机补全的一次性受限票据。 */
class OAuthCompletionTicket extends BaseModel
{
    protected $name = 'oauth_completion_ticket';
}
