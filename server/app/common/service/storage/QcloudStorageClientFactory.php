<?php
declare(strict_types=1);

namespace app\common\service\storage;

use Qcloud\Cos\Client;

/** Provider SDK assembly kept outside business storage drivers. */
final class QcloudStorageClientFactory
{
    public function make(array $account, array $space): Client
    {
        $credentials = (array)($account['resolved_credentials'] ?? []);
        return new Client([
            'scheme' => 'https',
            'region' => (string)($space['region'] ?? ''),
            'connect_timeout' => 10,
            'timeout' => 30,
            'retry' => 1,
            'allow_redirects' => false,
            'credentials' => [
                'secretId' => (string)($credentials['access_key'] ?? ''),
                'secretKey' => (string)($credentials['secret_key'] ?? ''),
            ],
        ]);
    }
}
