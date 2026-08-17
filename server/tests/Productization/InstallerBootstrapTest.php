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

$website = brandWebsiteDefaults(dirname(__DIR__, 2));
installerExpect($website['name'] === 'Peanut Admin', 'installer must load the canonical brand manifest');
installerExpect($website['web_logo'] === 'brand/logo.svg', 'installer must seed canonical asset paths');

echo "PB08A-INSTALL-001 bootstrap passed\n";
