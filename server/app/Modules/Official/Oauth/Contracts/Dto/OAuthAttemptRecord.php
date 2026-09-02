<?php
declare(strict_types=1);

namespace app\Modules\Official\Oauth\Contracts\Dto;

final readonly class OAuthAttemptRecord
{
    public function __construct(
        public int $id,
        public string $scene,
        public string $returnPath,
        public int $expiresAt,
        public ?int $usedAt,
    ) {}
}
