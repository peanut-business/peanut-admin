<?php
declare(strict_types=1);

namespace app\api\controller;

use app\api\logic\OfficialAccountLogic;

class OfficialAccountController extends BaseApiController
{
    public array $notNeedLogin = ['verify', 'callback'];

    public function verify()
    {
        $params = $this->request->get();
        if (!OfficialAccountLogic::verify($params)) {
            return response('invalid signature', 403, ['Content-Type' => 'text/plain; charset=utf-8']);
        }
        return response((string)($params['echostr'] ?? ''), 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public function callback()
    {
        $params = $this->request->get();
        if (!OfficialAccountLogic::verify($params)) {
            return response('invalid signature', 403, ['Content-Type' => 'text/plain; charset=utf-8']);
        }
        if (strtolower((string)($params['encrypt_type'] ?? '')) === 'aes') {
            return response('encrypted callback is not enabled', 501, ['Content-Type' => 'text/plain; charset=utf-8']);
        }
        try {
            $result = OfficialAccountLogic::handlePlain((string)$this->request->getContent());
        } catch (\Throwable) {
            return response('invalid message', 400, ['Content-Type' => 'text/plain; charset=utf-8']);
        }
        $contentType = $result === 'success' ? 'text/plain; charset=utf-8' : 'application/xml; charset=utf-8';
        return response($result, 200, ['Content-Type' => $contentType]);
    }
}
