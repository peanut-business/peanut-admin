<?php
declare(strict_types=1);

namespace app\api\application;

use app\Modules\Official\Oauth\Application\OfficialAccountReplyApplicationService;
use app\common\service\wechat\OfficialAccountService;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

class OfficialAccountApplicationService
{
    public function __construct(
        private readonly OfficialAccountReplyApplicationService $replies,
    ) {
    }

    public function verify(array $params, array $config): bool
    {
        return OfficialAccountService::verifySignature(
            (string)($config['token'] ?? ''),
            (string)($params['timestamp'] ?? ''),
            (string)($params['nonce'] ?? ''),
            (string)($params['signature'] ?? '')
        );
    }

    public function handlePlain(TenantSystemContext $context, string $xml): string
    {
        $message = OfficialAccountService::parsePlainMessage($xml);
        $reply = $this->replies->resolve($context, $message);
        if ($reply === null || trim((string)($reply['content'] ?? '')) === '') {
            return 'success';
        }
        return OfficialAccountService::textReplyXml($message, (string)$reply['content']);
    }
}
