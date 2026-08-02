<?php
declare(strict_types=1);

namespace app\common\model\auth;

use app\common\model\BaseModel;

class AdminDept extends BaseModel
{
    protected $name = 'admin_dept';
    protected $autoWriteTimestamp = false;
}
