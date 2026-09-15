<?php
declare(strict_types=1);

namespace app\modules\official\oauth\model;

use app\common\model\TenantOwnedModel;
/** 按 provider + client + subject 隔离的外部身份。 */
class OAuthIdentity extends TenantOwnedModel
{
    protected $name = 'oauth_identity';
}
