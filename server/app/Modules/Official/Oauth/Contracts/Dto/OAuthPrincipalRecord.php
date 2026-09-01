<?php
declare(strict_types=1);

namespace app\Modules\Official\Oauth\Contracts\Dto;

final readonly class OAuthPrincipalRecord
{
    public function __construct(
        public int $id,
        public int $memberId,
    ) {}
}
