<?php
declare(strict_types=1);

namespace app\common\logic;

class BaseLogic
{
    protected static string $error = '';

    public static function setError(string $error): void
    {
        static::$error = $error;
    }

    public static function getError(): string
    {
        return static::$error;
    }
}
