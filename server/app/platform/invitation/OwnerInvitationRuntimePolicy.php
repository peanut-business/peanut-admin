<?php
declare(strict_types=1);

namespace app\platform\invitation;

final readonly class OwnerInvitationRuntimePolicy
{
    private const PLAINTEXT_TOKEN_ENVIRONMENTS = ['local', 'development'];

    private function __construct(private string $environment)
    {
    }

    public static function fromEnvironment(string $environment): self
    {
        return new self(strtolower(trim($environment)));
    }

    public function allowsPlaintextTokenResponse(): bool
    {
        return in_array($this->environment, self::PLAINTEXT_TOKEN_ENVIRONMENTS, true);
    }

    public function assertIssuanceAllowed(OwnerInvitationDeliveryPort $delivery): void
    {
        if (!$this->allowsPlaintextTokenResponse() && !$delivery->isConfigured()) {
            throw TenantOwnerInvitationException::unavailable(
                'OWNER_INVITATION_DELIVERY_UNAVAILABLE',
                'Owner invitation delivery is not configured.'
            );
        }
    }
}
