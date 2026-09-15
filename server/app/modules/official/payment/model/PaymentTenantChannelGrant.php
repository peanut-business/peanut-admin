<?php
declare(strict_types=1);

namespace app\modules\official\payment\model;

use app\common\model\TenantOwnedModel;

final class PaymentTenantChannelGrant extends TenantOwnedModel
{
    protected $name = 'payment_tenant_channel_grant';
}
