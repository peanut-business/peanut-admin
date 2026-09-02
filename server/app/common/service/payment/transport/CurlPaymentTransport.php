<?php
declare(strict_types=1);

namespace app\common\service\payment\transport;

use app\common\service\http\OutboundHttpRequest;
use app\common\service\http\OutboundHttpTransport;
use app\common\service\payment\contract\PaymentTransportInterface;
use app\common\service\payment\dto\TransportResponse;

final class CurlPaymentTransport implements PaymentTransportInterface
{
    public function __construct(private readonly OutboundHttpTransport $transport)
    {
    }

    public function request(string $method, string $url, array $headers, string $body = ''): TransportResponse
    {
        $normalizedHeaders = [];
        foreach ($headers as $name => $value) {
            if (is_int($name) && is_string($value) && str_contains($value, ':')) {
                [$name, $value] = explode(':', $value, 2);
            }
            $normalizedHeaders[trim((string)$name)] = trim((string)$value);
        }
        $response = $this->transport->send(
            new OutboundHttpRequest(
                $method,
                $url,
                $normalizedHeaders,
                $body,
                timeoutSeconds: 30,
            ),
        );
        return new TransportResponse($response->status, $response->body, $response->headers);
    }
}
