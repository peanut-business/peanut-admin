<?php
declare(strict_types=1);

namespace app\common\contract\config;

interface WebsiteConfigStore
{
    /** @return array<string, mixed> */
    public function read(): array;

    /** @param array<string, string> $values */
    public function replaceAtomically(array $values): void;
}
