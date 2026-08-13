<?php
declare(strict_types=1);

namespace app\common\model\log;

use app\common\model\BaseModel;

class OperationLog extends BaseModel
{
    protected $name = 'operation_log';
    protected $type = ['tenant_id' => 'integer'];

    // 仅有 create_time，无 update_time
    protected $updateTime = false;
}
