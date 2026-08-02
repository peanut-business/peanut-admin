<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\model\finance\RechargeOrder;

/**
 * 充值退款支付网关。
 *
 * 不提供模拟成功分支：配置缺失、签名失败和渠道明确拒绝会抛出普通异常；
 * 请求结果未知时使用 ERROR_RESULT_UNKNOWN，调用方保持退款中并等待查询收敛。
 */
class RefundGatewayService
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    /** 渠道可能已受理、但本次未取得可信响应；调用方必须保持退款中并等待查询收敛。 */
    public const ERROR_RESULT_UNKNOWN = 10001;

    private const WECHAT_REFUND_URL = 'https://api.mch.weixin.qq.com/v3/refund/domestic/refunds';
    private const ALIPAY_GATEWAY_URL = 'https://openapi.alipay.com/gateway.do';

    /**
     * @return array{status:string,transaction_id:string,raw:array}
     */
    public static function refund(array $order, string $refundSn, float $refundAmount): array
    {
        if ($refundAmount <= 0) {
            throw new \RuntimeException('订单金额异常');
        }

        $result = match ((int)($order['pay_way'] ?? 0)) {
            RechargeOrder::PAY_WAY_WECHAT => self::wechatRefund($order, $refundSn, $refundAmount),
            RechargeOrder::PAY_WAY_ALIPAY => self::alipayRefund($order, $refundSn, $refundAmount),
            default => throw new \RuntimeException('支付方式异常'),
        };

        return self::guardSuccessfulResult($result);
    }

    /**
     * 查询已发起退款的最终状态。
     *
     * @return array{status:string,transaction_id:string,raw:array}
     */
    public static function query(array $order, string $refundSn): array
    {
        if ($refundSn === '') {
            throw new \RuntimeException('退款流水号缺失');
        }

        $result = match ((int)($order['pay_way'] ?? 0)) {
            RechargeOrder::PAY_WAY_WECHAT => self::wechatQuery($refundSn),
            RechargeOrder::PAY_WAY_ALIPAY => self::alipayQuery($order, $refundSn),
            default => throw new \RuntimeException('支付方式异常'),
        };

        return self::guardSuccessfulResult($result);
    }

    /** 渠道声称成功但未返回交易号时结果不可置信，保持退款中等待后续查询。 */
    private static function guardSuccessfulResult(array $result): array
    {
        if (($result['status'] ?? '') === self::STATUS_SUCCESS
            && trim((string)($result['transaction_id'] ?? '')) === '') {
            throw new \RuntimeException('支付渠道退款交易流水号缺失', self::ERROR_RESULT_UNKNOWN);
        }

        return $result;
    }

    /** @return array{status:string,transaction_id:string,raw:array} */
    private static function wechatRefund(array $order, string $refundSn, float $refundAmount): array
    {
        $config = ConfigService::get('pay');
        if ((int)($config['wx_pay_status'] ?? 0) !== 1) {
            throw new \RuntimeException('微信支付未开启');
        }

        $mchId = trim((string)($config['wx_pay_mch_id'] ?? ''));
        $certPath = self::resolveFilePath((string)($config['wx_pay_cert_path'] ?? ''));
        $keyPath = self::resolveFilePath((string)($config['wx_pay_cert_key_path'] ?? ''));
        $transactionId = trim((string)($order['transaction_id'] ?? ''));
        if ($mchId === '' || $certPath === '' || $keyPath === '') {
            throw new \RuntimeException('微信支付配置不完整');
        }
        if ($transactionId === '') {
            throw new \RuntimeException('微信支付交易流水号缺失');
        }

        $certificate = file_get_contents($certPath);
        $privateKeyText = file_get_contents($keyPath);
        if ($certificate === false || $privateKeyText === false) {
            throw new \RuntimeException('微信支付商户证书读取失败');
        }
        $certificateInfo = openssl_x509_parse($certificate);
        $serialNo = strtoupper((string)($certificateInfo['serialNumberHex'] ?? ''));
        if ($serialNo === '') {
            throw new \RuntimeException('无法读取微信支付商户证书序列号');
        }
        $privateKey = openssl_pkey_get_private($privateKeyText);
        if ($privateKey === false) {
            throw new \RuntimeException('微信支付商户私钥无效');
        }

        $payload = [
            'transaction_id' => $transactionId,
            'out_refund_no' => $refundSn,
            'reason' => '充值订单退款',
            'amount' => [
                'refund' => self::yuanToCent($refundAmount),
                'total' => self::yuanToCent((float)($order['order_amount'] ?? 0)),
                'currency' => 'CNY',
            ],
        ];
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new \RuntimeException('微信退款请求生成失败');
        }

        $timestamp = (string)time();
        $nonce = bin2hex(random_bytes(16));
        $urlPath = '/v3/refund/domestic/refunds';
        $message = "POST\n{$urlPath}\n{$timestamp}\n{$nonce}\n{$body}\n";
        $signature = '';
        if (!openssl_sign($message, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('微信退款请求签名失败');
        }
        $authorization = 'WECHATPAY2-SHA256-RSA2048 '
            . 'mchid="' . $mchId . '",'
            . 'nonce_str="' . $nonce . '",'
            . 'signature="' . base64_encode($signature) . '",'
            . 'timestamp="' . $timestamp . '",'
            . 'serial_no="' . $serialNo . '"';

        [$httpCode, $responseBody] = self::httpPost(
            self::WECHAT_REFUND_URL,
            $body,
            [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: ' . $authorization,
                'User-Agent: PeanutAdmin/1.0',
            ],
            self::ERROR_RESULT_UNKNOWN
        );
        $response = json_decode($responseBody, true);
        if (!is_array($response)) {
            throw new \RuntimeException(
                '微信退款响应格式异常',
                ($httpCode >= 200 && $httpCode < 300) || $httpCode >= 500
                    ? self::ERROR_RESULT_UNKNOWN
                    : 0
            );
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \RuntimeException(
                '微信支付:' . (string)($response['message'] ?? $response['code'] ?? '退款请求失败'),
                $httpCode >= 500 || in_array($httpCode, [408, 429], true)
                    ? self::ERROR_RESULT_UNKNOWN
                    : 0
            );
        }

        $channelStatus = strtoupper((string)($response['status'] ?? ''));
        if (in_array($channelStatus, ['CLOSED', 'ABNORMAL'], true)) {
            throw new \RuntimeException('微信支付:退款请求失败（' . $channelStatus . '）');
        }

        // 微信退款受理不代表退款成功，最终状态应由回调或查询任务确认。
        return [
            'status' => self::STATUS_PENDING,
            'transaction_id' => (string)($response['refund_id'] ?? ''),
            'raw' => $response,
        ];
    }

    /** @return array{status:string,transaction_id:string,raw:array} */
    private static function wechatQuery(string $refundSn): array
    {
        $config = ConfigService::get('pay');
        if ((int)($config['wx_pay_status'] ?? 0) !== 1) {
            throw new \RuntimeException('微信支付未开启');
        }

        $mchId = trim((string)($config['wx_pay_mch_id'] ?? ''));
        $certPath = self::resolveFilePath((string)($config['wx_pay_cert_path'] ?? ''));
        $keyPath = self::resolveFilePath((string)($config['wx_pay_cert_key_path'] ?? ''));
        if ($mchId === '' || $certPath === '' || $keyPath === '') {
            throw new \RuntimeException('微信支付配置不完整');
        }

        $certificate = file_get_contents($certPath);
        $privateKeyText = file_get_contents($keyPath);
        if ($certificate === false || $privateKeyText === false) {
            throw new \RuntimeException('微信支付商户证书读取失败');
        }
        $certificateInfo = openssl_x509_parse($certificate);
        $serialNo = strtoupper((string)($certificateInfo['serialNumberHex'] ?? ''));
        $privateKey = openssl_pkey_get_private($privateKeyText);
        if ($serialNo === '' || $privateKey === false) {
            throw new \RuntimeException('微信支付商户证书或私钥无效');
        }

        $urlPath = '/v3/refund/domestic/refunds/' . rawurlencode($refundSn);
        $timestamp = (string)time();
        $nonce = bin2hex(random_bytes(16));
        $message = "GET\n{$urlPath}\n{$timestamp}\n{$nonce}\n\n";
        $signature = '';
        if (!openssl_sign($message, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('微信退款查询签名失败');
        }
        $authorization = 'WECHATPAY2-SHA256-RSA2048 '
            . 'mchid="' . $mchId . '",'
            . 'nonce_str="' . $nonce . '",'
            . 'signature="' . base64_encode($signature) . '",'
            . 'timestamp="' . $timestamp . '",'
            . 'serial_no="' . $serialNo . '"';

        [$httpCode, $responseBody] = self::httpGet(
            'https://api.mch.weixin.qq.com' . $urlPath,
            [
                'Accept: application/json',
                'Authorization: ' . $authorization,
                'User-Agent: PeanutAdmin/1.0',
            ]
        );
        $response = json_decode($responseBody, true);
        if (!is_array($response)) {
            throw new \RuntimeException('微信退款查询响应格式异常');
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \RuntimeException(
                '微信支付:' . (string)($response['message'] ?? $response['code'] ?? '退款查询失败')
            );
        }

        $channelStatus = strtoupper((string)($response['status'] ?? ''));
        $status = match ($channelStatus) {
            'SUCCESS' => self::STATUS_SUCCESS,
            'CLOSED', 'ABNORMAL' => self::STATUS_FAILED,
            default => self::STATUS_PENDING,
        };

        return [
            'status' => $status,
            'transaction_id' => (string)($response['refund_id'] ?? ''),
            'raw' => $response,
        ];
    }

    /** @return array{status:string,transaction_id:string,raw:array} */
    private static function alipayRefund(array $order, string $refundSn, float $refundAmount): array
    {
        $config = ConfigService::get('pay');
        if ((int)($config['ali_pay_status'] ?? 0) !== 1) {
            throw new \RuntimeException('支付宝支付未开启');
        }

        $appId = trim((string)($config['ali_pay_app_id'] ?? ''));
        $privateKeyText = self::normalizePrivateKey((string)($config['ali_pay_private_key'] ?? ''));
        $publicKeyText = self::normalizePublicKey((string)($config['ali_pay_public_key'] ?? ''));
        if ($appId === '' || $privateKeyText === '' || $publicKeyText === '') {
            throw new \RuntimeException('支付宝支付配置不完整');
        }

        $privateKey = openssl_pkey_get_private($privateKeyText);
        if ($privateKey === false) {
            throw new \RuntimeException('支付宝应用私钥无效');
        }

        $params = [
            'app_id' => $appId,
            'method' => 'alipay.trade.refund',
            'format' => 'JSON',
            'charset' => 'utf-8',
            'sign_type' => 'RSA2',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0',
            'biz_content' => json_encode([
                'out_trade_no' => (string)($order['sn'] ?? ''),
                'refund_amount' => number_format($refundAmount, 2, '.', ''),
                'out_request_no' => $refundSn,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
        ksort($params);
        $signContent = self::queryStringForSign($params);
        $signature = '';
        if (!openssl_sign($signContent, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('支付宝退款请求签名失败');
        }
        $params['sign'] = base64_encode($signature);

        [$httpCode, $responseBody] = self::httpPost(
            self::ALIPAY_GATEWAY_URL,
            http_build_query($params, '', '&', PHP_QUERY_RFC3986),
            ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'],
            self::ERROR_RESULT_UNKNOWN
        );
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \RuntimeException(
                '支付宝退款网络请求失败（HTTP ' . $httpCode . '）',
                $httpCode >= 500 || in_array($httpCode, [408, 429], true)
                    ? self::ERROR_RESULT_UNKNOWN
                    : 0
            );
        }

        $decoded = json_decode($responseBody, true);
        $response = $decoded['alipay_trade_refund_response'] ?? null;
        if (!is_array($decoded) || !is_array($response)) {
            throw new \RuntimeException('支付宝退款响应格式异常', self::ERROR_RESULT_UNKNOWN);
        }
        try {
            self::verifyAlipayResponse(
                $responseBody,
                $decoded,
                $publicKeyText,
                'alipay_trade_refund_response'
            );
        } catch (\Throwable $e) {
            throw new \RuntimeException($e->getMessage(), self::ERROR_RESULT_UNKNOWN, $e);
        }

        $code = (string)($response['code'] ?? '');
        $statusMessage = (string)($response['msg'] ?? '');
        $message = (string)($response['sub_msg'] ?? $statusMessage ?: '退款请求失败');
        if ($code !== '10000' || $statusMessage !== 'Success') {
            throw new \RuntimeException('支付宝:' . $message);
        }

        $fundChange = (string)($response['fund_change'] ?? $response['fundChange'] ?? '');
        return [
            'status' => $fundChange === 'Y' ? self::STATUS_SUCCESS : self::STATUS_PENDING,
            'transaction_id' => (string)($response['trade_no'] ?? $response['tradeNo'] ?? ''),
            'raw' => $response,
        ];
    }

    /** @return array{status:string,transaction_id:string,raw:array} */
    private static function alipayQuery(array $order, string $refundSn): array
    {
        $config = ConfigService::get('pay');
        if ((int)($config['ali_pay_status'] ?? 0) !== 1) {
            throw new \RuntimeException('支付宝支付未开启');
        }

        $appId = trim((string)($config['ali_pay_app_id'] ?? ''));
        $privateKeyText = self::normalizePrivateKey((string)($config['ali_pay_private_key'] ?? ''));
        $publicKeyText = self::normalizePublicKey((string)($config['ali_pay_public_key'] ?? ''));
        if ($appId === '' || $privateKeyText === '' || $publicKeyText === '') {
            throw new \RuntimeException('支付宝支付配置不完整');
        }
        $privateKey = openssl_pkey_get_private($privateKeyText);
        if ($privateKey === false) {
            throw new \RuntimeException('支付宝应用私钥无效');
        }

        $params = [
            'app_id' => $appId,
            'method' => 'alipay.trade.fastpay.refund.query',
            'format' => 'JSON',
            'charset' => 'utf-8',
            'sign_type' => 'RSA2',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0',
            'biz_content' => json_encode([
                'out_trade_no' => (string)($order['sn'] ?? ''),
                'out_request_no' => $refundSn,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
        ksort($params);
        $signature = '';
        if (!openssl_sign(
            self::queryStringForSign($params),
            $signature,
            $privateKey,
            OPENSSL_ALGO_SHA256
        )) {
            throw new \RuntimeException('支付宝退款查询签名失败');
        }
        $params['sign'] = base64_encode($signature);

        [$httpCode, $responseBody] = self::httpPost(
            self::ALIPAY_GATEWAY_URL,
            http_build_query($params, '', '&', PHP_QUERY_RFC3986),
            ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded']
        );
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \RuntimeException('支付宝退款查询网络请求失败（HTTP ' . $httpCode . '）');
        }

        $decoded = json_decode($responseBody, true);
        $responseKey = 'alipay_trade_fastpay_refund_query_response';
        $response = $decoded[$responseKey] ?? null;
        if (!is_array($decoded) || !is_array($response)) {
            throw new \RuntimeException('支付宝退款查询响应格式异常');
        }
        self::verifyAlipayResponse($responseBody, $decoded, $publicKeyText, $responseKey);

        if ((string)($response['code'] ?? '') !== '10000') {
            return [
                'status' => self::STATUS_PENDING,
                'transaction_id' => '',
                'raw' => $response,
            ];
        }

        $channelStatus = strtoupper((string)($response['refund_status'] ?? ''));
        $status = match ($channelStatus) {
            'REFUND_SUCCESS' => self::STATUS_SUCCESS,
            'REFUND_FAIL', 'REFUND_FAILED', 'FAILED', 'CLOSED' => self::STATUS_FAILED,
            default => self::STATUS_PENDING,
        };

        return [
            'status' => $status,
            'transaction_id' => (string)($response['trade_no'] ?? ''),
            'raw' => $response,
        ];
    }

    /** @return array{0:int,1:string} */
    private static function httpPost(
        string $url,
        string $body,
        array $headers,
        int $networkErrorCode = 0
    ): array
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('服务器未安装 cURL 扩展，无法调用支付渠道');
        }
        $curl = curl_init($url);
        if ($curl === false) {
            throw new \RuntimeException('支付渠道请求初始化失败');
        }
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $response = curl_exec($curl);
        if ($response === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new \RuntimeException('支付渠道网络异常:' . $error, $networkErrorCode);
        }
        $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        return [$httpCode, (string)$response];
    }

    /** @return array{0:int,1:string} */
    private static function httpGet(string $url, array $headers): array
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('服务器未安装 cURL 扩展，无法调用支付渠道');
        }
        $curl = curl_init($url);
        if ($curl === false) {
            throw new \RuntimeException('支付渠道请求初始化失败');
        }
        curl_setopt_array($curl, [
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $response = curl_exec($curl);
        if ($response === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new \RuntimeException('支付渠道网络异常:' . $error);
        }
        $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        return [$httpCode, (string)$response];
    }

    private static function yuanToCent(float $amount): int
    {
        return (int)round($amount * 100);
    }

    private static function resolveFilePath(string $configuredPath): string
    {
        $configuredPath = trim($configuredPath);
        if ($configuredPath === '') {
            return '';
        }
        $candidates = [$configuredPath];
        if (!str_starts_with($configuredPath, '/')) {
            $relative = ltrim($configuredPath, '/\\');
            $candidates[] = root_path($relative);
            $candidates[] = public_path($relative);
        }
        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }
        return '';
    }

    private static function normalizePrivateKey(string $key): string
    {
        $key = trim(str_replace('\\n', "\n", $key));
        if ($key === '') {
            return '';
        }
        if (str_contains($key, 'BEGIN')) {
            return $key;
        }
        $body = preg_replace('/\s+/', '', $key) ?? '';
        return "-----BEGIN PRIVATE KEY-----\n"
            . chunk_split($body, 64, "\n")
            . "-----END PRIVATE KEY-----";
    }

    private static function normalizePublicKey(string $key): string
    {
        $key = trim(str_replace('\\n', "\n", $key));
        if ($key === '') {
            return '';
        }
        if (str_contains($key, 'BEGIN')) {
            return $key;
        }
        $body = preg_replace('/\s+/', '', $key) ?? '';
        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split($body, 64, "\n")
            . "-----END PUBLIC KEY-----";
    }

    private static function queryStringForSign(array $params): string
    {
        $pairs = [];
        foreach ($params as $key => $value) {
            if ($value !== '' && $value !== null) {
                $pairs[] = $key . '=' . $value;
            }
        }
        return implode('&', $pairs);
    }

    private static function verifyAlipayResponse(
        string $rawResponse,
        array $decoded,
        string $publicKeyText,
        string $responseKey
    ): void {
        $signature = base64_decode((string)($decoded['sign'] ?? ''), true);
        $signedContent = self::extractJsonObject($rawResponse, $responseKey);
        $publicKey = openssl_pkey_get_public($publicKeyText);
        if ($signature === false || $signedContent === '' || $publicKey === false
            || openssl_verify($signedContent, $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw new \RuntimeException('支付宝退款响应验签失败');
        }
    }

    /** 提取支付宝实际签名的响应节点原文，避免重新编码 JSON 改变签名内容。 */
    private static function extractJsonObject(string $json, string $key): string
    {
        $keyPosition = strpos($json, '"' . $key . '"');
        if ($keyPosition === false) {
            return '';
        }
        $start = strpos($json, '{', $keyPosition);
        if ($start === false) {
            return '';
        }

        $depth = 0;
        $inString = false;
        $escaped = false;
        $length = strlen($json);
        for ($index = $start; $index < $length; $index++) {
            $char = $json[$index];
            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }
                continue;
            }
            if ($char === '"') {
                $inString = true;
            } elseif ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($json, $start, $index - $start + 1);
                }
            }
        }
        return '';
    }
}
