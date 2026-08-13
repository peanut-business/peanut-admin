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
    'kernel_version' => (string)env('PEANUT_MODULE_KERNEL_VERSION', '1.0.0'),
    'frontend_components' => [],
    'registered_client_keys' => ['admin-web', 'platform-web'],
];
