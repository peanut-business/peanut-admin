<?php
declare(strict_types=1);

namespace app\common\model\auth;

use app\common\model\BaseModel;
use think\model\concern\SoftDelete;

class Admin extends BaseModel
{
    use SoftDelete;
    protected $name = 'admin';
    protected $deleteTime = 'delete_time';
    protected $hidden = ['password', 'salt'];

    public function roles()
    {
        return $this->belongsToMany(SystemRole::class, 'admin_role', 'role_id', 'admin_id');
    }

    public function setPasswordAttr(string $value, array $data): string
    {
        $salt = $data['salt'] ?? '';
        return md5(md5($value) . $salt);
    }
}
