<?php
declare(strict_types=1);

namespace app\common\service\dict;

use app\common\service\dict\contract\DictionaryQuery;
use app\common\service\dict\contract\TenantDictionaryCommands;
use PeanutAdmin\Kernel\Dictionary\Application\DictionaryService;

final class DictionaryRuntimeFactory
{
    public static function service(): DictionaryQuery&TenantDictionaryCommands
    {
        $tenant = new ThinkPhpTenantDictionaryProvider();
        $system = new ThinkPhpSystemDictionaryProvider();
        return new DictionaryRuntime(new DictionaryService($tenant, $tenant, $system), $system);
    }

    private function __construct() {}
}
