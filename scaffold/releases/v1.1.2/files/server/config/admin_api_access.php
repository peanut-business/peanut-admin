<?php
declare(strict_types=1);

/**
 * Versioned exceptions to the default-deny Tenant Admin API policy.
 *
 * Permission-protected api/admin routes are deliberately not listed here:
 * their exact normalized path must exist as an enabled pa_system_menu.perms
 * node. Keep both exception sets small and method-specific.
 */
return [
    'version' => 1,
    'public' => [
        'POST api/user/login',
        'POST api/user/logout',
        'POST admin/login/login',
        'POST admin/login/logout',
        'POST api/tenant/session/login',
        'POST api/tenant/session/select',
        'POST api/tenant/session/switch',
        'POST api/tenant/session/logout',
    ],
    'authenticated' => [
        'POST api/user/info',
        'POST api/user/menu',
        'GET api/admin/login/info',
        'GET api/admin/menu/route',
        'GET api/admin/admin/self',
        'POST api/admin/admin/editself',
    ],
    // Platform credentials and permission keys stay in their own audience.
    'platform_public' => [
        'POST api/platform/session/login',
        'POST api/platform/session/refresh',
        'POST api/platform/session/logout',
    ],
    'platform_authenticated' => [
        'GET api/platform/session/info',
    ],
];
