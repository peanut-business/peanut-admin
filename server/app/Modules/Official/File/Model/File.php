<?php
declare(strict_types=1);

namespace app\Modules\Official\File\Model;

use app\common\model\TenantOwnedModel;
use think\model\concern\SoftDelete;

class File extends TenantOwnedModel
{
    use SoftDelete;
    protected $name = 'file';
    protected $deleteTime = 'delete_time';
}
