<?php
declare(strict_types=1);

namespace app\modules\official\task\contracts;

interface TaskBootstrapCommands
{
    /** @param list<array<string,mixed>> $defaults */
    public function seedDefaults(array $defaults): void;
}
