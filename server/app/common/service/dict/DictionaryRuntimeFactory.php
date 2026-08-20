<?php
declare(strict_types=1);

namespace app\common\service\dict;

use PeanutAdmin\Kernel\Dictionary\Application\DictionaryService;

final class DictionaryRuntimeFactory
{
    public static function service(): DictionaryService
    {
        $tenant = new ThinkPhpTenantDictionaryProvider();
        return new DictionaryService($tenant, $tenant, new ThinkPhpSystemDictionaryProvider());
    }

    private function __construct() {}
}
