<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
        'crontab'         => \app\command\Crontab::class,
        'crontab:demo'    => \app\command\CrontabDemo::class,
        'refund:reconcile' => \app\command\RefundReconcile::class,
        'generator:cleanup' => \app\command\GeneratorCleanup::class,
        'tenant-task:work' => \app\command\TenantTaskWorker::class,
        'ops-backup:task' => \app\command\OpsBackupTask::class,
        'ops-restore:task' => \app\command\OpsRestoreTask::class,
        'ops-upgrade:task' => \app\command\OpsUpgradeTask::class,
        'module:install'   => \app\command\ModuleInstall::class,
        'module:create'    => \app\command\ModuleCreate::class,
        'module:pack'      => \app\command\ModulePack::class,
        'bundle:pack'      => \app\command\BundlePack::class,
        'module:install-package' => \app\command\ModuleInstallPackage::class,
        'module:update-package' => \app\command\ModuleUpdatePackage::class,
        'module:uninstall-package' => \app\command\ModuleUninstallPackage::class,
        'module:sync' => \app\command\ModuleSync::class,
        'plugin:install'   => \app\command\PluginInstall::class,
        'plugin:reconcile' => \app\command\PluginReconcile::class,
        'plugin:make'      => \app\command\PluginMake::class,
        'plugin:lock'      => \app\command\PluginLock::class,
        'plugin:upgrade'   => \app\command\PluginUpgrade::class,
        'plugin:rollback'  => \app\command\PluginRollback::class,
        'plugin:uninstall' => \app\command\PluginUninstall::class,
        'tenant-module:apply-profile' => \app\command\TenantModuleProfile::class,
    ],
    // Every command that may run from the Tenant scheduler must declare its owning Module.
    // Core commands use the built-in `core` capability; application Modules register their key.
    'module_commands' => [
        'crontab:demo' => 'official.task',
        'refund:reconcile' => 'official.payment',
    ],
];
