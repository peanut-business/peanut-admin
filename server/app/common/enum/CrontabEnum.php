<?php
declare(strict_types=1);

namespace app\common\enum;

/**
 * 定时任务枚举
 */
class CrontabEnum
{
    // 类型
    const CRONTAB = 1; // 定时任务

    // 状态
    const START = 1; // 运行
    const STOP  = 2; // 停止
    const ERROR = 3; // 错误

    const TYPE_DESC = [
        self::CRONTAB => '定时任务',
    ];

    const STATUS_DESC = [
        self::START => '运行',
        self::STOP  => '停止',
        self::ERROR => '错误',
    ];
}
