<?php
declare(strict_types=1);

namespace app\common\service\notice;

use PeanutAdmin\NotificationSms\Application\VerificationCodeSecret as CoreVerificationCodeSecret;

/** @deprecated Application compatibility delegate for the core verification-code primitive. */
final class VerificationCodeSecret
{
    public static function hash(string $code): string
    {
        return CoreVerificationCodeSecret::hash($code);
    }

    public static function matches(string $code, string $hash): bool
    {
        return CoreVerificationCodeSecret::matches($code, $hash);
    }
}
