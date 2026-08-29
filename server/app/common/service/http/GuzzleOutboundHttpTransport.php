<?php
declare(strict_types=1);

namespace app\common\service\http;

use app\common\service\runtime\OperationalLog;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/** Single outbound HTTP adapter with bounded retry and secret-free diagnostics. */
final readonly class GuzzleOutboundHttpTransport implements OutboundHttpTransport
{
    private ClientInterface $client;

    public function __construct(?ClientInterface $client = null)
    {
        $this->client = $client ?? new Client();
    }

    public function send(OutboundHttpRequest $request): OutboundHttpResponse
    {
        $attempts = $request->retrySafe ? 2 : 1;
        $last = null;
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = $this->client->request(
                    strtoupper($request->method),
                    $request->url,
                    array_filter([
                        'allow_redirects' => $request->allowRedirects,
                        'body' => $request->body !== '' ? $request->body : null,
                        'connect_timeout' => $request->connectTimeoutSeconds,
                        'headers' => $request->headers,
                        'http_errors' => false,
                        'multipart' => $request->multipart !== [] ? $request->multipart : null,
                        'sink' => $request->sink,
                        'timeout' => $request->timeoutSeconds,
                        'verify' => true,
                    ], static fn(mixed $value): bool => $value !== null),
                );
                $status = $response->getStatusCode();
                if ($request->retrySafe && $attempt < $attempts && $status >= 500) {
                    continue;
                }
                $headers = [];
                foreach ($response->getHeaders() as $name => $values) {
                    $headers[strtolower($name)] = implode(', ', $values);
                }
                return new OutboundHttpResponse(
                    $status,
                    $request->sink === null ? (string)$response->getBody() : '',
                    $headers,
                );
            } catch (GuzzleException $exception) {
                $last = $exception;
                if ($attempt < $attempts) {
                    continue;
                }
            }
        }

        OperationalLog::warning('outbound_http_unavailable', [
            'method' => strtoupper($request->method),
            'host' => (string)(parse_url($request->url, PHP_URL_HOST) ?: 'unknown'),
            'exception' => $last === null ? 'unknown' : $last::class,
        ]);
        throw new OutboundHttpException($last);
    }
}
