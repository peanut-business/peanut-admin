<?php
declare(strict_types=1);
return [
    'secret' => env('JWT_SECRET', 'peanut-admin-change-this-in-production'),
    'expire' => (int) env('JWT_EXPIRE', 7200),
];
