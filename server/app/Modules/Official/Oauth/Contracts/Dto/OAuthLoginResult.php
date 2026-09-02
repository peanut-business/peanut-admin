<?php
declare(strict_types=1);

namespace app\Modules\Official\Oauth\Contracts\Dto;

use app\Modules\Official\Member\Contracts\Dto\MemberIdentitySnapshot;

final readonly class OAuthLoginResult
{
    public function __construct(
        public bool $completed,
        public MemberIdentitySnapshot $member,
        public ?string $completionTicket = null,
        public int $expiresIn = 0,
        public bool $needProfile = false,
        public bool $needMobile = false,
        public ?string $returnPath = null,
    ) {}

    public function withReturnPath(string $returnPath): self
    {
        return new self(
            $this->completed,
            $this->member,
            $this->completionTicket,
            $this->expiresIn,
            $this->needProfile,
            $this->needMobile,
            $returnPath,
        );
    }
}
