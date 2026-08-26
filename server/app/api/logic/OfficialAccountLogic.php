<?php
declare(strict_types=1);

namespace app\api\logic;

use app\Modules\Official\Oauth\Service\OfficialAccountReplyLogic;
use app\common\service\wechat\OfficialAccountService;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

class OfficialAccountLogic
{
    public static function verify(array $params, array $config): bool
    {
        return OfficialAccountService::verifySignature(
            (string)($config['token'] ?? ''),
            (string)($params['timestamp'] ?? ''),
            (string)($params['nonce'] ?? ''),
            (string)($params['signature'] ?? '')
        );
    }

    public static function handlePlain(TenantSystemContext $context, string $xml): string
    {
        $message = OfficialAccountService::parsePlainMessage($xml);
        $reply = OfficialAccountReplyLogic::resolve($context, $message);
        if ($reply === null || trim((string)($reply['content'] ?? '')) === '') {
            return 'success';
        }
        return OfficialAccountService::textReplyXml($message, (string)$reply['content']);
    }
}
