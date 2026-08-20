<?php
declare(strict_types=1);

namespace app\common\logic;

class BaseLogic
{
    /** @var array<class-string,string> */
    private static array $errors = [];

    /** 清理上一次逻辑调用留下的失败信息。每个公开操作应在入口调用。 */
    protected static function clearError(): void
    {
        unset(self::$errors[static::class]);
    }

    public static function setError(string $error): void
    {
        self::$errors[static::class] = $error;
    }

    /** 记录失败信息并返回统一的 false 结果。 */
    protected static function fail(\Throwable|string $error): false
    {
        self::setError($error instanceof \Throwable ? $error->getMessage() : $error);
        return false;
    }

    public static function getError(): string
    {
        return self::$errors[static::class] ?? '';
    }
}
