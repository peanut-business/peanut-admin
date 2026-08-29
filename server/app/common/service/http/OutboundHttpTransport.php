<?php
declare(strict_types=1);

namespace app\common\service\http;

interface OutboundHttpTransport
{
    public function send(OutboundHttpRequest $request): OutboundHttpResponse;
}
