<?php
declare(strict_types=1);

namespace app\api\controller;

use app\api\logic\OfficialAccountLogic;
use app\common\service\external\ExternalTenantResolver;

class OfficialAccountController extends BaseApiController
{
    public array $notNeedLogin = ['verify', 'callback'];

    public function verify()
    {
        $params = $this->request->get();
        try {
            ExternalTenantResolver::production()->verifiedCallback(
                ExternalTenantResolver::WECHAT_OFFICIAL_CALLBACK,
                (string)$this->request->route('binding'),
                'wechat.official.verify',
                $this->operationId(),
                static fn(array $config): bool => OfficialAccountLogic::verify($params, $config),
            );
        } catch (\Throwable) {
            return response('callback rejected', 403, ['Content-Type' => 'text/plain; charset=utf-8']);
        }
        return response((string)($params['echostr'] ?? ''), 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public function callback()
    {
        $params = $this->request->get();
        try {
            $resolution = ExternalTenantResolver::production()->verifiedCallback(
                ExternalTenantResolver::WECHAT_OFFICIAL_CALLBACK,
                (string)$this->request->route('binding'),
                'wechat.official.callback',
                $this->operationId(),
                static function (array $config) use ($params): bool {
                    return strtolower((string)($params['encrypt_type'] ?? '')) !== 'aes'
                        && OfficialAccountLogic::verify($params, $config);
                },
            );
            $result = OfficialAccountLogic::handlePlain(
                $resolution->context,
                (string)$this->request->getContent(),
            );
        } catch (\Throwable) {
            return response('callback rejected', 403, ['Content-Type' => 'text/plain; charset=utf-8']);
        }
        $contentType = $result === 'success' ? 'text/plain; charset=utf-8' : 'application/xml; charset=utf-8';
        return response($result, 200, ['Content-Type' => $contentType]);
    }

    private function operationId(): string
    {
        $requestId = trim((string)$this->request->header('X-Request-Id', ''));
        return $requestId !== '' ? $requestId : bin2hex(random_bytes(16));
    }
}
