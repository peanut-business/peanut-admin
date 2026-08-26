<?php
declare(strict_types=1);

namespace app\Modules\Official\Payment\Infrastructure;

use app\Modules\Official\Payment\Contracts\PaymentChannelGrantCommands;
use app\common\service\payment\PaymentChannelGrantService;

final class ThinkPhpPaymentChannelGrantCommands implements PaymentChannelGrantCommands
{
    public function grantTenantChannel(
        int $tenantId,
        string $provider,
        int $externalBindingId,
        string $merchantAccountRef = '',
        string $merchantGroupRef = ''
    ): int {
        return PaymentChannelGrantService::grant(
            $tenantId,
            $provider,
            $externalBindingId,
            $merchantAccountRef,
            $merchantGroupRef
        );
    }

    public function revokeTenantChannel(int $tenantId, string $provider, int $externalBindingId): void
    {
        PaymentChannelGrantService::revoke($tenantId, $provider, $externalBindingId);
    }
}
