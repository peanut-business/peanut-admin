<?php
declare(strict_types=1);

namespace app\common\model\member;

use think\Model;

class MemberTagRelation extends Model
{
    protected $name = 'member_tag_relation';
    // 无软删除、无自动时间戳
    protected $autoWriteTimestamp = false;
}
