<?php
declare(strict_types=1);

$peanutRouteApplication = 'adminapi';
$serverRoot = dirname(__DIR__, 3);

require $serverRoot . '/route/app.php';
require $serverRoot . '/route/tenant.php';
require $serverRoot . '/route/admin.php';

foreach ([
    'official_article.php',
    'official_file.php',
    'official_notification.php',
    'official_oauth.php',
    'official_payment.php',
    'official_member.php',
    'official_task.php',
    'official_import_export.php',
] as $moduleRoute) {
    require $serverRoot . '/route/' . $moduleRoute;
}

unset($peanutRouteApplication, $serverRoot, $moduleRoute);
