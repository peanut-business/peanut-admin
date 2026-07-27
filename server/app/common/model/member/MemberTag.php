<?php
declare(strict_types=1);

namespace app\common\model\member;

use app\common\model\BaseModel;
use think\model\concern\SoftDelete;

class MemberTag extends BaseModel
{
    use SoftDelete;
    protected $name       = 'member_tag';
    protected $deleteTime = 'delete_time';
}
