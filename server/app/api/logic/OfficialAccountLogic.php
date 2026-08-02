<?php
declare(strict_types=1);

namespace app\api\logic;

use app\adminapi\logic\setting\OfficialAccountReplyLogic;
use app\common\service\ConfigService;
use app\common\service\wechat\OfficialAccountService;

class OfficialAccountLogic
{
    public static function verify(array $params): bool
    {
        return OfficialAccountService::verifySignature(
            (string)ConfigService::get('oa_setting', 'token', ''),
            (string)($params['timestamp'] ?? ''),
            (string)($params['nonce'] ?? ''),
            (string)($params['signature'] ?? '')
        );
    }

    public static function handlePlain(string $xml): string
    {
        $message = OfficialAccountService::parsePlainMessage($xml);
        $reply = OfficialAccountReplyLogic::resolve($message);
        if ($reply === null || trim((string)($reply['content'] ?? '')) === '') {
            return 'success';
        }
        return OfficialAccountService::textReplyXml($message, (string)$reply['content']);
    }
}
