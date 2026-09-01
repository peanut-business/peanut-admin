<?php
declare(strict_types=1);

namespace app\Modules\Official\Task\Model;

use app\common\model\TenantOwnedModel;
use think\model\concern\SoftDelete;

/**
 * 定时任务模型
 */
class Crontab extends TenantOwnedModel
{
    use SoftDelete;
    protected $name = 'crontab';
    protected $deleteTime = 'delete_time';
}
