<?php
declare(strict_types=1);

namespace app\Modules\Official\Task\Model;

use app\common\enum\CrontabEnum;
use app\common\model\BaseModel;
use think\model\concern\SoftDelete;

/**
 * 定时任务模型
 */
class Crontab extends BaseModel
{
    use SoftDelete;
    protected $name = 'crontab';
    protected $deleteTime = 'delete_time';

    /** 类型描述 */
    public function getTypeDescAttr($value, $data): string
    {
        return CrontabEnum::TYPE_DESC[$data['type'] ?? 0] ?? '';
    }

    /** 状态描述 */
    public function getStatusDescAttr($value, $data): string
    {
        return CrontabEnum::STATUS_DESC[$data['status'] ?? 0] ?? '';
    }

    /** 最后执行时间格式化 */
    public function getLastTimeAttr($value): string
    {
        return empty($value) ? '' : date('Y-m-d H:i:s', (int) $value);
    }
}
