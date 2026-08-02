<?php
declare(strict_types=1);

namespace app\common\model\auth;

use app\common\model\BaseModel;
use think\model\concern\SoftDelete;

class SystemRole extends BaseModel
{
    use SoftDelete;

    protected $name = 'system_role';
    protected $deleteTime = 'delete_time';

    public function menus()
    {
        return $this->belongsToMany(SystemMenu::class, 'system_role_menu', 'menu_id', 'role_id');
    }
}
