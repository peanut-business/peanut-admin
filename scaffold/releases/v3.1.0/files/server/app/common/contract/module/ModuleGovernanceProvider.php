<?php
declare(strict_types=1);

namespace app\common\contract\module;

use app\platform\service\module\DeployedTenantModuleRegistry;

interface ModuleGovernanceProvider
{
    public function registry(): DeployedTenantModuleRegistry;

    public function pluginLifecycle(): PluginLifecycleCommands;

    public function qualification(): ModuleQualificationQuery;

}
