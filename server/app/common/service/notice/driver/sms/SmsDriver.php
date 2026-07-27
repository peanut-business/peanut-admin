<?php
declare(strict_types=1);

namespace app\common\service\notice\driver\sms;

/**
 * 短信驱动抽象基类
 */
abstract class SmsDriver
{
    protected string $error = '';

    abstract public function __construct(array $config);

    /**
     * 发送短信
     * @param string              $mobile      手机号
     * @param string              $templateCode 服务商模板 code
     * @param array<string,mixed> $vars         模板变量
     */
    abstract public function send(string $mobile, string $templateCode, array $vars): bool;

    public function getError(): string
    {
        return $this->error;
    }
}
