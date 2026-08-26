<?php
declare(strict_types=1);

namespace app\Modules\Official\File\Model;

use app\common\model\BaseModel;
use think\model\concern\SoftDelete;

class FileCate extends BaseModel
{
    use SoftDelete;
    protected $name = 'file_cate';
    protected $deleteTime = 'delete_time';
}
