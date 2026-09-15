<?php
declare(strict_types=1);

namespace app\modules\official\oauth\contracts\dto;

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
