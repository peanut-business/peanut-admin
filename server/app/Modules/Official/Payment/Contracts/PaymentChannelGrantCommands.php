<?php
declare(strict_types=1);

namespace app\Modules\Official\Payment\Contracts;

interface PaymentChannelGrantCommands
{
    public function grantTenantChannel(
        int $tenantId,
        string $provider,
        int $externalBindingId,
        string $merchantAccountRef = '',
        string $merchantGroupRef = ''
    ): int;

    public function revokeTenantChannel(int $tenantId, string $provider, int $externalBindingId): void;
}
