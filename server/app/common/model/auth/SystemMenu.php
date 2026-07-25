<?php
declare(strict_types=1);

namespace app\common\model\auth;

use app\common\model\BaseModel;

class SystemMenu extends BaseModel
{
    protected $name = 'system_menu';
    protected $autoWriteTimestamp = false;
}
