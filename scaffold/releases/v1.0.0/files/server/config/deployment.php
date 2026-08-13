<?php
declare(strict_types=1);

return [
    // Instance-wide developer and maintenance tools require an explicit mode.
    // Missing or unknown values remain closed instead of becoming standalone.
    'mode' => env('DEPLOYMENT_MODE'),
];
