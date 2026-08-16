<?php
declare(strict_types=1);

// ThinkPHP reads process-level configuration through PHP_* names. Deployers
// configure only the conventional unprefixed keys; every PHP entry point uses
// this bridge before the framework boots.
$environmentKeys = [
    'APP_ENV',
    'APP_DEBUG',
    'DEPLOYMENT_MODE',
    'JWT_SECRET',
    'JWT_EXPIRE',
    'TENANT_IDENTIFIER_HMAC_KEY',
    'PLATFORM_IDENTIFIER_HMAC_KEY',
    'DB_DRIVER',
    'DB_TYPE',
    'DB_HOST',
    'DB_PORT',
    'DB_NAME',
    'DB_USER',
    'DB_PASS',
    'DB_CHARSET',
    'DB_PREFIX',
    'ASYNC_SIGNING_KEY',
    'PEANUT_MODULE_ROOTS',
    'PEANUT_PLUGIN_LOCK',
    'PEANUT_MODULE_KERNEL_VERSION',
];

foreach ($environmentKeys as $environmentKey) {
    $alias = 'PHP_' . $environmentKey;
    if (getenv($alias) !== false) {
        continue;
    }
    $value = getenv($environmentKey);
    if ($value === false) {
        continue;
    }
    putenv($alias . '=' . $value);
    $_ENV[$alias] = $value;
    $_SERVER[$alias] = $value;
}

unset($alias, $environmentKey, $environmentKeys, $value);
