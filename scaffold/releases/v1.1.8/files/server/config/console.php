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
        'module:install'   => \app\command\ModuleInstall::class,
        'plugin:install'   => \app\command\PluginInstall::class,
        'plugin:upgrade'   => \app\command\PluginUpgrade::class,
        'plugin:rollback'  => \app\command\PluginRollback::class,
        'plugin:uninstall' => \app\command\PluginUninstall::class,
    ],
];
