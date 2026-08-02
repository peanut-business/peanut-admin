<?php
declare(strict_types=1);

namespace app\common\service;

/** 定时任务只允许调用显式注册且非调度器自身的控制台命令。 */
class CrontabCommandService
{
    public static function allowedCommands(): array
    {
        $commands = array_keys((array)config('console.commands', []));
        return array_values(array_filter(
            $commands,
            static fn(string $command): bool => $command !== 'crontab'
        ));
    }

    public static function assertAllowed(string $command): void
    {
        if (!in_array($command, self::allowedCommands(), true)) {
            throw new \RuntimeException('定时任务命令未注册或不允许调度');
        }
    }
}
