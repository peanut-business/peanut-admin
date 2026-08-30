<?php
declare(strict_types=1);

namespace app\common\service\decoration;

use app\common\model\decoration\DecoratePage;

final class DecorationTenantRepository
{
    public static function pages()
    {
        return DecoratePage::where([]);
    }

    private function __construct()
    {
    }
}
