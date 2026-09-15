<?php
declare(strict_types=1);

namespace app\modules\official\notification\contracts;

final readonly class VerificationResult
{
    public function __construct(
        public bool $accepted,
        public string $error = '',
    ) {
    }
}
