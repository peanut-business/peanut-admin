<?php
declare(strict_types=1);

namespace app\platform\identity;

interface PlatformOperatorIdentityPort
{
    /** Resolve only an opaque credential issued by a trusted platform authentication boundary. */
    public function requireActive(string $credential): PlatformOperatorIdentity;
}
