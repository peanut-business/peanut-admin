<?php
declare(strict_types=1);

namespace app\common\model\auth;

use app\common\model\BaseModel;

class AdminRole extends BaseModel
{
    protected $name = 'admin_role';
    protected $autoWriteTimestamp = false;
}
