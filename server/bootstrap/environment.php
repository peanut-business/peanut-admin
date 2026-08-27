<?php
declare(strict_types=1);

/**
 * Load the one backend configuration source before ThinkPHP boots.
 *
 * Normal runtime uses server/.env. Isolated tests and qualification runs may
 * select a sibling server/.env.<run-id> through PEANUT_SERVER_ENV_FILE. The
 * selected file must exist. Normal settings are file-owned; fresh-install
 * identities are the only process-only inputs and may never be persisted.
 */

if (!function_exists('peanutBackendEnvironmentKeys')) {
    /** @return list<string> */
    function peanutBackendEnvironmentKeys(): array
    {
        return [
            'APP_ENV',
            'APP_DEBUG',
            'PEANUT_DEPLOYMENT_TARGET',
            'PEANUT_DATABASE_RESOURCE_ID',
            'PEANUT_DATABASE_ENDPOINT_ID',
            'PEANUT_DATABASE_CONSUMER',
            'PEANUT_RESOURCE_LEASE_PROOF',
            'DEPLOYMENT_MODE',
            'PUBLIC_DEFAULT_TENANT_FALLBACK',
            'PLATFORM_HOSTS',
            'TENANT_ADMIN_HOSTS',
            'OWNER_INVITATION_DELIVERY_MODE',
            'JWT_SECRET',
            'JWT_EXPIRE',
            'ADMIN_TOKEN_EXPIRE',
            'ADMIN_TOKEN_RENEW_BEFORE',
            'ADMIN_PASSWORD_ERROR_TIMES',
            'ADMIN_LOGIN_LOCK_MINUTES',
            'TENANT_IDENTIFIER_HMAC_KEY',
            'PLATFORM_IDENTIFIER_HMAC_KEY',
            'ADMIN_INITIAL_EMAIL',
            'ADMIN_INITIAL_PASSWORD',
            'PLATFORM_INITIAL_EMAIL',
            'PLATFORM_INITIAL_PASSWORD',
            'PEANUT_STORAGE_CREDENTIAL_MASTER_KEY',
            'PEANUT_DEMO_MODE',
            'PEANUT_DEMO_TENANT_A_EMAIL',
            'PEANUT_DEMO_TENANT_B_EMAIL',
            'PEANUT_DEMO_SHARED_PASSWORD',
            'PEANUT_DEMO_TENANT_A_HOST',
            'PEANUT_DEMO_TENANT_B_HOST',
            'PEANUT_DEMO_DOCS_URL',
            'DB_DRIVER',
            'DB_TYPE',
            'DB_HOST',
            'DB_PORT',
            'DB_NAME',
            'DB_USER',
            'DB_PASS',
            'DB_ROOT_PASS',
            'DB_CHARSET',
            'DB_PREFIX',
            'ASYNC_SIGNING_KEY',
            'ASYNC_WORKER_LIMIT',
            'DEFAULT_LANG',
            'PROJECT_VERSION',
            'PEANUT_MODULE_ROOTS',
            'PEANUT_PLUGIN_LOCK',
            'PEANUT_MODULE_KERNEL_VERSION',
            'PEANUT_MODULE_TRUSTED_KEYS_JSON',
            'PEANUT_INSTALLATION_MODE',
            'PEANUT_INSTALLATION_SETUP_TOKEN',
            'PEANUT_INSTALLATION_OFFICIAL_MODULES',
        ];
    }

    /** @return list<string> */
    function peanutTransientInstallationKeys(): array
    {
        return [
            'ADMIN_INITIAL_EMAIL',
            'ADMIN_INITIAL_PASSWORD',
            'PLATFORM_INITIAL_EMAIL',
            'PLATFORM_INITIAL_PASSWORD',
        ];
    }

    function peanutBackendEnvironmentPath(): string
    {
        $serverRoot = dirname(__DIR__);
        $selected = getenv('PEANUT_SERVER_ENV_FILE');
        $path = $selected === false || trim($selected) === ''
            ? $serverRoot . '/.env'
            : trim($selected);

        if (!str_starts_with($path, '/')) {
            throw new RuntimeException('BACKEND_ENVIRONMENT_PATH_NOT_ABSOLUTE');
        }
        $directory = realpath(dirname($path));
        if ($directory === false || $directory !== realpath($serverRoot)) {
            throw new RuntimeException('BACKEND_ENVIRONMENT_PATH_OUTSIDE_SERVER');
        }
        if (basename($path) === '.env.example'
            || preg_match('/^\.env(?:\.[a-z0-9][a-z0-9-]{0,63})?$/D', basename($path)) !== 1) {
            throw new RuntimeException('BACKEND_ENVIRONMENT_NAME_INVALID');
        }

        return $path;
    }

    /** @param array<string,mixed> $values */
    function peanutApplyBackendEnvironment(array $values): void
    {
        $process = getenv();
        if (is_array($process)) {
            foreach (array_keys($process) as $name) {
                if (is_string($name) && preg_match(
                    '/^PHP_(ENV_NAME|APP_|DB_|JWT_|DEPLOYMENT_MODE$|PUBLIC_DEFAULT_TENANT_FALLBACK$|PLATFORM_|TENANT_|ADMIN_|OWNER_INVITATION_|PEANUT_|ASYNC_|DEFAULT_LANG$|PROJECT_VERSION$)/D',
                    $name
                ) === 1) {
                    throw new RuntimeException('BACKEND_ENVIRONMENT_LEGACY_PREFIX_FORBIDDEN:' . substr($name, 4));
                }
            }
        }
        $managed = array_fill_keys(peanutBackendEnvironmentKeys(), true);
        $transient = array_fill_keys(peanutTransientInstallationKeys(), true);
        foreach ($managed as $key => $_) {
            $legacy = getenv('PHP_' . $key);
            if ($legacy !== false) {
                throw new RuntimeException("BACKEND_ENVIRONMENT_LEGACY_PREFIX_FORBIDDEN:{$key}");
            }
            $existing = getenv($key);
            if ($existing === false) {
                continue;
            }
            if (isset($transient[$key])) {
                $declared = $values[$key] ?? '';
                if ((string)$declared !== '') {
                    throw new RuntimeException("BACKEND_ENVIRONMENT_TRANSIENT_IDENTITY_PERSISTED:{$key}");
                }
                continue;
            }
            if (!array_key_exists($key, $values)) {
                throw new RuntimeException("BACKEND_ENVIRONMENT_PROCESS_VALUE_UNDECLARED:{$key}");
            }
            $declared = $values[$key];
            if (!is_string($declared) && !is_int($declared) && !is_float($declared) && !is_bool($declared)) {
                throw new RuntimeException("BACKEND_ENVIRONMENT_VALUE_INVALID:{$key}");
            }
            $declared = is_bool($declared) ? ($declared ? 'true' : 'false') : (string)$declared;
            if (!hash_equals((string)$existing, $declared)) {
                throw new RuntimeException("BACKEND_ENVIRONMENT_CONFLICT:{$key}");
            }
        }

        foreach ($values as $key => $value) {
            if (!is_string($key) || !isset($managed[$key])) {
                continue;
            }
            if (!is_string($value) && !is_int($value) && !is_float($value) && !is_bool($value)) {
                throw new RuntimeException("BACKEND_ENVIRONMENT_VALUE_INVALID:{$key}");
            }
            $value = is_bool($value) ? ($value ? 'true' : 'false') : (string)$value;
            if (isset($transient[$key])) {
                if ($value !== '') {
                    throw new RuntimeException("BACKEND_ENVIRONMENT_TRANSIENT_IDENTITY_PERSISTED:{$key}");
                }
                continue;
            }
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    function peanutLoadBackendEnvironment(): void
    {
        $path = peanutBackendEnvironmentPath();
        if (!is_file($path)) {
            throw new RuntimeException('BACKEND_ENVIRONMENT_FILE_MISSING');
        }
        if (is_link($path)) {
            throw new RuntimeException('BACKEND_ENVIRONMENT_SYMLINK_FORBIDDEN');
        }
        $mode = fileperms($path);
        if (!is_int($mode) || ($mode & 0077) !== 0) {
            throw new RuntimeException('BACKEND_ENVIRONMENT_PERMISSIONS_TOO_OPEN');
        }
        $values = parse_ini_file($path, false, INI_SCANNER_RAW);
        if (!is_array($values)) {
            throw new RuntimeException('BACKEND_ENVIRONMENT_PARSE_FAILED');
        }
        peanutApplyBackendEnvironment($values);

        $basename = basename($path);
        if ($basename !== '.env') {
            $name = substr($basename, strlen('.env.'));
            $_ENV['ENV_NAME'] = $name;
            $_SERVER['ENV_NAME'] = $name;
        }
    }
}

peanutLoadBackendEnvironment();
