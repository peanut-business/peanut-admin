<?php
declare(strict_types=1);

namespace app\common\model\dept;

use app\common\model\BaseModel;
use think\model\concern\SoftDelete;

class Jobs extends BaseModel
{
    use SoftDelete;

    protected $name = 'jobs';
    protected $deleteTime = 'delete_time';

    public function getStatusDescAttr($value, array $data): string
    {
        return !empty($data['status']) ? '正常' : '停用';
    }
}
