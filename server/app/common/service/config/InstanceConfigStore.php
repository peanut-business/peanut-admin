<?php
declare(strict_types=1);

namespace app\common\service\config;

interface InstanceConfigStore
{
    public function get(string $type, string $name = '', mixed $default = null): mixed;

    public function set(string $type, string $name, mixed $value): void;

    /** @param array<string, mixed> $data */
    public function setManyAtomically(string $type, array $data): void;
}
