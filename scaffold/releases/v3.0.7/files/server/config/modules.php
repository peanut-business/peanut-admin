<?php
declare(strict_types=1);

$roots = array_values(array_filter(array_map(
    'trim',
    explode(',', (string)env('PEANUT_MODULE_ROOTS', ''))
)));

return [
    // Module roots are an explicit deployment input. An empty list keeps the
    // platform control plane available while TenantModule management fails closed.
    'roots' => $roots,
    'plugin_lock' => (string)env('PEANUT_PLUGIN_LOCK', '../plugins.lock'),
    'kernel_version' => (string)env('PEANUT_MODULE_KERNEL_VERSION', '1.0.0'),
    'frontend_components' => [
        'official.article.cate',
        'official.article.list',
        'official.file.library',
        'official.notification.channel',
        'official.notification.template',
        'official.notification.log',
        'official.oauth.channel',
        'official.payment.settings',
        'official.payment.recharge',
        'official.payment.refund',
        'official.member.list',
        'official.member.tag',
        'official.member.account-log',
        'official.task.schedules',
    ],
    'registered_client_keys' => ['admin-web', 'platform-web'],
];
