<?php
declare(strict_types=1);

namespace app\platform\identity;

final class UnavailablePlatformOperatorIdentityPort implements PlatformOperatorIdentityPort
{
    public function requireActive(string $credential): PlatformOperatorIdentity
    {
        throw new \DomainException('PLATFORM_OPERATOR_AUTHENTICATION_UNAVAILABLE');
    }
}
