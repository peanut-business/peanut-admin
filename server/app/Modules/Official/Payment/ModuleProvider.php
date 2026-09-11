<?php
declare(strict_types=1);

namespace app\Modules\Official\Payment;

use app\Modules\Official\Payment\Application\RechargeApplicationService;
use app\Modules\Official\Payment\Contracts\PaymentChannelGrantCommands;
use app\Modules\Official\Payment\Contracts\RechargeCommands;
use app\Modules\Official\Payment\Contracts\RechargeQueries;
use app\Modules\Official\Payment\Contracts\RefundReconciliationCommands;
use app\Modules\Official\Payment\Infrastructure\ThinkPhpPaymentChannelGrantCommands;
use app\Modules\Official\Payment\Infrastructure\ThinkPhpRefundReconciliationCommands;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;

final class ModuleProvider implements ModuleProviderContract
{
    public function moduleKey(): string
    {
        return 'official.payment';
    }

    public function bindings(): array
    {
        return [
            PaymentChannelGrantCommands::class => ThinkPhpPaymentChannelGrantCommands::class,
            RechargeCommands::class => RechargeApplicationService::class,
            RechargeQueries::class => RechargeApplicationService::class,
            RefundReconciliationCommands::class => ThinkPhpRefundReconciliationCommands::class,
        ];
    }
}
