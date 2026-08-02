<?php
declare(strict_types=1);

namespace app\common\model\auth;

use app\common\model\BaseModel;

class AdminJobs extends BaseModel
{
    protected $name = 'admin_jobs';
    protected $autoWriteTimestamp = false;
}
