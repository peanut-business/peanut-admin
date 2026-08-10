<?php
declare(strict_types=1);

namespace app\common\service\payment\transport;

use app\common\service\payment\contract\PaymentTransportInterface;
use app\common\service\payment\dto\TransportResponse;

final class CurlPaymentTransport implements PaymentTransportInterface
{
    public function request(string $method, string $url, array $headers, string $body = ''): TransportResponse
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('服务器未安装 cURL 扩展，无法调用支付渠道');
        }
        $curl = curl_init($url);
        if ($curl === false) {
            throw new \RuntimeException('支付渠道请求初始化失败');
        }
        $responseHeaders = [];
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$responseHeaders): int {
                $length = strlen($line);
                $separator = strpos($line, ':');
                if ($separator !== false) {
                    $name = strtolower(trim(substr($line, 0, $separator)));
                    if ($name !== '') {
                        $responseHeaders[$name] = trim(substr($line, $separator + 1));
                    }
                }
                return $length;
            },
        ]);
        if ($body !== '') {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }
        $response = curl_exec($curl);
        if ($response === false) {
            $message = curl_error($curl);
            curl_close($curl);
            throw new \RuntimeException('支付渠道网络异常:' . $message);
        }
        $statusCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        return new TransportResponse($statusCode, (string)$response, $responseHeaders);
    }
}
