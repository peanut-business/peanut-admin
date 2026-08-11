<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/database/install.php';

function installerExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

foreach (['', 'short1', 'onlyletterslong', '123456789012'] as $weakPassword) {
    try {
        validateInitialAdminPassword($weakPassword);
        throw new RuntimeException('weak initial password must fail');
    } catch (RuntimeException $exception) {
        installerExpect(
            $exception->getMessage() === 'ADMIN_INITIAL_PASSWORD 至少 12 位且必须同时包含字母和数字',
            'weak password must fail at the installer boundary'
        );
    }
}

$password = 'FreshInstall2026';
$salt = '0123456789abcdef';
$seed = file_get_contents(dirname(__DIR__, 2) . '/database/init.sql');
installerExpect(is_string($seed), 'installer test must read init.sql');
$replaced = replaceInitialAdminSeed($seed, $password, $salt);
$digest = md5(md5($password) . $salt);
installerExpect(str_contains($replaced, "'{$digest}', '{$salt}'"), 'installer must inject the supplied password digest');
installerExpect(
    !str_contains($replaced, "MD5(CONCAT(MD5('admin123456')"),
    'known password expression must not reach the database'
);
installerExpect(str_contains($replaced, '密码：admin123456'), 'installer must only replace the executable seed');

try {
    replaceInitialAdminSeed('SELECT 1;', $password, $salt);
    throw new RuntimeException('missing admin seed must fail');
} catch (RuntimeException $exception) {
    installerExpect($exception->getMessage() === '管理员 seed 与安装合同不一致', 'seed drift must fail closed');
}

$website = brandWebsiteDefaults(dirname(__DIR__, 2));
installerExpect($website['name'] === 'Peanut Admin', 'installer must load the canonical brand manifest');
installerExpect($website['web_logo'] === 'brand/logo.svg', 'installer must seed canonical asset paths');

echo "PB08A-INSTALL-001 bootstrap passed\n";
