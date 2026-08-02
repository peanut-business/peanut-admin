<?php
declare(strict_types=1);

namespace app\common\service\oauth\contract;

use app\common\service\oauth\dto\OAuthProfile;

interface OAuthTransportInterface
{
    /** 浏览器场景授权地址；小程序场景不调用。 */
    public function authorizationUrl(
        string $scene,
        array $config,
        string $redirectUri,
        string $state
    ): string;

    /** 用一次性 code 换取已标准化微信身份。 */
    public function exchange(string $scene, array $config, string $code): OAuthProfile;
}
