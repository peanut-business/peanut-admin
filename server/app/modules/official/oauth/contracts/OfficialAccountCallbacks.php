<?php
declare(strict_types=1);

namespace app\modules\official\oauth\contracts;

use PeanutAdmin\Kernel\Context\TenantSystemContext;

interface OfficialAccountCallbacks
{
    public function verify(array $params, array $config): bool;

    public function handlePlain(TenantSystemContext $context, string $xml): string;
}
