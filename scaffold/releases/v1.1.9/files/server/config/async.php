<?php
declare(strict_types=1);

return [
    'signing_key' => env('ASYNC_SIGNING_KEY', ''),
    'private_storage_root' => env(
        'ASYNC_PRIVATE_STORAGE_ROOT',
        app()->getRuntimePath() . 'private'
    ),
    'worker_limit' => (int)env('ASYNC_WORKER_LIMIT', 25),
];
