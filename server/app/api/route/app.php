<?php
declare(strict_types=1);

$peanutRouteApplication = 'api';
$serverRoot = dirname(__DIR__, 3);

require $serverRoot . '/route/public_api.php';

foreach ([
    'official_file.php',
    'official_notification.php',
    'official_oauth.php',
    'official_payment.php',
    'official_member.php',
] as $moduleRoute) {
    require $serverRoot . '/route/' . $moduleRoute;
}

unset($peanutRouteApplication, $serverRoot, $moduleRoute);
