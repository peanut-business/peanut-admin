<?php
declare(strict_types=1);

namespace app\Modules\Official\Oauth\Contracts\Dto;

final readonly class OAuthCompletionRecord
{
    public function __construct(
        public int $id,
        public int $memberId,
        public bool $needProfile,
        public bool $needMobile,
        public int $expiresAt,
        public ?int $usedAt,
    ) {}
}
