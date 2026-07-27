<?php
declare(strict_types=1);

namespace app\common\model\notice;

use app\common\model\BaseModel;

/**
 * 通知发送记录模型
 */
class NoticeLog extends BaseModel
{
    protected $name = 'notice_log';
    protected $updateTime = false; // 日志只插入不更新

    /** 状态：待发送 */
    public const STATUS_PENDING = 0;
    /** 状态：发送成功 */
    public const STATUS_SUCCESS = 1;
    /** 状态：发送失败 */
    public const STATUS_FAIL = 2;

    /** 渠道：短信 */
    public const CHANNEL_SMS = 1;
    /** 渠道：邮件 */
    public const CHANNEL_EMAIL = 2;
    /** 渠道：推送 */
    public const CHANNEL_PUSH = 3;
}
