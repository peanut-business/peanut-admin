<?php
declare(strict_types=1);

namespace app\common\model\dict;

use app\common\model\TenantOwnedModel;
use think\model\concern\SoftDelete;

class DictType extends TenantOwnedModel
{
    use SoftDelete;
    protected $name = 'dict_type';
    protected $deleteTime = 'delete_time';
}
