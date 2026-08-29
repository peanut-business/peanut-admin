<?php
declare(strict_types=1);

namespace app\Modules\Official\Notification\Model;

use app\common\model\TenantOwnedModel;

/**
 * 通知场景模型。
 */
class NoticeScene extends TenantOwnedModel
{
    protected $name = 'notice_scene';

    public const STATUS_DISABLED = 0;
    public const STATUS_ENABLED = 1;
}
