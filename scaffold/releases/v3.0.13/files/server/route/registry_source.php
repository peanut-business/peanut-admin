<?php
declare(strict_types=1);

/** Read the complete route registry for static contracts without executing it. */
function peanut_route_registry_source(string $serverRoot): string
{
    $routeRoot = rtrim($serverRoot, '/') . '/route';
    $files = [
        'app.php',
        'platform.php',
        'tenant.php',
        'admin.php',
        'public_api.php',
        'official_article.php',
        'official_file.php',
        'official_notification.php',
        'official_oauth.php',
        'official_payment.php',
        'official_member.php',
        'official_task.php',
        'official_import_export.php',
    ];

    $source = '';
    foreach ($files as $file) {
        $path = $routeRoot . '/' . $file;
        if (!is_file($path)) {
            throw new RuntimeException('Route registry file is missing: ' . $file);
        }
        $source .= "\n/* route/{$file} */\n" . (string)file_get_contents($path);
    }
    return $source;
}
