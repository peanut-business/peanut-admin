<?php
declare(strict_types=1);

namespace app\modules\official\oauth\contracts\dto;

final readonly class OAuthPrincipalRecord
{
    public function __construct(
        public int $id,
        public int $memberId,
    ) {}
}
