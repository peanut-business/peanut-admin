<?php
declare(strict_types=1);

namespace app\common\model\member;

use think\Model;

class MemberBalanceLog extends Model
{
    protected $name           = 'member_balance_log';
    protected $autoWriteTimestamp = 'int';
    protected $createTime     = 'create_time';
    protected $updateTime     = false; // 仅记录插入时间
}
