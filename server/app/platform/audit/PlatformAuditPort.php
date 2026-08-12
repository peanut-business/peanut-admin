<?php
declare(strict_types=1);

namespace app\platform\audit;

use app\platform\context\PlatformOperatorContext;

/** Injection boundary only; the platform audit Runtime is owned by its separate PM01 line. */
interface PlatformAuditPort
{
    /** @param array<string, mixed> $metadata */
    public function append(
        PlatformOperatorContext $context,
        string $eventType,
        string $action,
        string $outcome,
        array $metadata = []
    ): void;
}
