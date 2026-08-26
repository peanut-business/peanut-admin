<?php
declare(strict_types=1);

namespace app\Modules\Official\Oauth\Model;

use app\common\model\BaseModel;

/** 同一微信 UnionID 在本系统内的唯一会员归属。 */
class OAuthPrincipal extends BaseModel
{
    protected $name = 'oauth_principal';
}
