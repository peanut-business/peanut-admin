<?php
declare(strict_types=1);

namespace app\common\service\oauth\dto;

final class OAuthProfile
{
    public function __construct(
        private string $subject,
        private string $unionId = '',
        private string $nickname = '',
        private string $avatar = ''
    ) {
        $this->subject = trim($this->subject);
        $this->unionId = trim($this->unionId);
        $this->nickname = trim($this->nickname);
        $this->avatar = trim($this->avatar);
        if ($this->subject === '') {
            throw new \RuntimeException('微信授权结果缺少 openid');
        }
    }

    public function subject(): string { return $this->subject; }
    public function unionId(): string { return $this->unionId; }
    public function nickname(): string { return $this->nickname; }
    public function avatar(): string { return $this->avatar; }
}
