<?php
declare(strict_types=1);

namespace app\modules\official\member\model;

use app\common\model\TenantOwnedModel;
use think\model\concern\SoftDelete;

class MemberTag extends TenantOwnedModel
{
    use SoftDelete;
    protected $name       = 'member_tag';
    protected $deleteTime = 'delete_time';
}
