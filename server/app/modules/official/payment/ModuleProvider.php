<?php
declare(strict_types=1);

namespace app\modules\official\payment;

use app\modules\official\payment\services\RechargeApplicationService;
use app\modules\official\payment\contracts\PaymentChannelGrantCommands;
use app\modules\official\payment\contracts\RechargeCommands;
use app\modules\official\payment\contracts\RechargeQueries;
use app\modules\official\payment\contracts\RefundReconciliationCommands;
use app\modules\official\payment\infrastructure\ThinkPhpPaymentChannelGrantCommands;
use app\modules\official\payment\infrastructure\ThinkPhpRefundReconciliationCommands;
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
