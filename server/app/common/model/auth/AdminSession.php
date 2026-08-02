<?php
declare(strict_types=1);

namespace app\common\model\auth;

use app\common\model\BaseModel;

class AdminSession extends BaseModel
{
    protected $name = 'admin_session';
    protected $autoWriteTimestamp = false;

    public function admin()
    {
        return $this->hasOne(Admin::class, 'id', 'admin_id');
    }
}
