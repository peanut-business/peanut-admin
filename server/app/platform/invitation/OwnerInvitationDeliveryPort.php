<?php
declare(strict_types=1);

namespace app\platform\invitation;

interface OwnerInvitationDeliveryPort
{
    public function deliver(OwnerInvitationDelivery $delivery): OwnerInvitationDeliveryResult;
}
