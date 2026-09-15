<?php

declare(strict_types=1);

namespace app\modules\fixture\delivery_record\model;

use app\common\model\TenantOwnedModel;

final class DeliveryRecord extends TenantOwnedModel
{
    protected $name = 'fixture_delivery_record';
    protected $type = ['tenant_id' => 'integer'];
}
