<?php
declare(strict_types=1);

namespace app\common\model\auth;

use app\common\model\BaseModel;

class SystemRole extends BaseModel
{
    protected $name = 'system_role';

    public function menus()
    {
        return $this->belongsToMany(SystemMenu::class, 'system_role_menu', 'menu_id', 'role_id');
    }
}
