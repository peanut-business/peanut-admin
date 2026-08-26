<?php
declare(strict_types=1);

namespace app\Modules\Official\Notification\Contracts;

final readonly class VerificationResult
{
    public function __construct(
        public bool $accepted,
        public string $error = '',
    ) {
    }
}
