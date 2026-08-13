<?php
declare(strict_types=1);

namespace app\common\service\external;

final class ExternalTenantResolutionException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('EXTERNAL_CALLBACK_REJECTED');
    }
}
