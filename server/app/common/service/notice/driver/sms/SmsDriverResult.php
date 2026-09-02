<?php
declare(strict_types=1);

namespace app\common\service\notice\driver\sms;

final readonly class SmsDriverResult
{
    /** @param array<string, mixed> $receipt */
    public function __construct(
        public bool $success,
        public string $error,
        public array $receipt,
    ) {
    }
}
