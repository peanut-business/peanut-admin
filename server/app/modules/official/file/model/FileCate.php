<?php
declare(strict_types=1);

namespace app\modules\official\file\model;

use app\common\model\TenantOwnedModel;
use think\model\concern\SoftDelete;

class FileCate extends TenantOwnedModel
{
    use SoftDelete;
    protected $name = 'file_cate';
    protected $deleteTime = 'delete_time';
}
