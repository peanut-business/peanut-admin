<?php
declare(strict_types=1);

namespace app\common\service\notice\driver\sms;

interface SmsDriver
{
    /** @param array<string, mixed> $variables */
    public function send(string $mobile, string $templateCode, array $variables): SmsDriverResult;
}
