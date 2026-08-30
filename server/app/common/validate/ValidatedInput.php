<?php
declare(strict_types=1);

namespace app\common\validate;

/** Immutable result of a successful request-input validation. */
final readonly class ValidatedInput
{
    /** @param array<string,mixed> $values */
    public function __construct(private array $values)
    {
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return $this->values;
    }
}
