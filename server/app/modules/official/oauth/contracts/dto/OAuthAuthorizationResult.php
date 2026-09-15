<?php
declare(strict_types=1);

namespace app\modules\official\oauth\contracts\dto;

final readonly class OAuthAuthorizationResult
{
    public function __construct(
        public string $authorizationUrl,
        public int $expiresIn,
    ) {}

    public function toArray(): array
    {
        return [
            'authorization_url' => $this->authorizationUrl,
            'expires_in' => $this->expiresIn,
        ];
    }
}
