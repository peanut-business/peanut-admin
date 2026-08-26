<?php
declare(strict_types=1);

namespace app\Modules\Official\Oauth\Model;

use app\common\model\BaseModel;
use think\model\concern\SoftDelete;

class OfficialAccountReply extends BaseModel
{
    use SoftDelete;

    protected $name = 'official_account_reply';
    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = 0;
}
