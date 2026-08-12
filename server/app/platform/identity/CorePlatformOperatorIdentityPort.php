<?php
declare(strict_types=1);

namespace app\platform\identity;

use app\platform\service\PlatformOperatorSessionService;

/** Bridges governance services to the independently validated platform session audience. */
final readonly class CorePlatformOperatorIdentityPort implements PlatformOperatorIdentityPort
{
    public function __construct(private PlatformOperatorSessionService $sessions)
    {
    }

    public function requireActive(string $credential): PlatformOperatorIdentity
    {
        return $this->sessions
            ->context($credential, 'platform-governance-' . bin2hex(random_bytes(12)))
            ->identity();
    }
}
