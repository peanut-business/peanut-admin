<?php
declare(strict_types=1);

namespace app\common\model\dict;

use app\common\model\BaseModel;
use think\model\concern\SoftDelete;

class DictData extends BaseModel
{
    use SoftDelete;
    protected $name = 'dict_data';
    protected $deleteTime = 'delete_time';
}
