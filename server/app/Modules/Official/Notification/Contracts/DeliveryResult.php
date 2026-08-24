<?php
declare(strict_types=1);

namespace app\Modules\Official\Notification\Contracts;

final readonly class DeliveryResult
{
    /** @param array<string,mixed> $receipt */
    public function __construct(
        public bool $success,
        public string $provider,
        public string $error = '',
        public array $receipt = [],
    ) {
    }
}
