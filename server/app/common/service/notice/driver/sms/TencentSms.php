<?php
declare(strict_types=1);

namespace app\common\service\notice\driver\sms;

/**
 * 腾讯云短信驱动（TC3-HMAC-SHA256 签名）
 * 文档：https://cloud.tencent.com/document/product/382/52071
 *
 * 配置 key（pa_config type=notice，name=sms_tencent，value=JSON）：
 *   secret_id, secret_key, sdk_app_id, sign_name, region
 */
class TencentSms extends SmsDriver
{
    private string $secretId;
    private string $secretKey;
    private string $sdkAppId;
    private string $signName;
    private string $region;

    public function __construct(array $config)
    {
        $this->secretId  = (string) ($config['secret_id']  ?? '');
        $this->secretKey = (string) ($config['secret_key'] ?? '');
        $this->sdkAppId  = (string) ($config['sdk_app_id'] ?? '');
        $this->signName  = (string) ($config['sign_name']  ?? '');
        $this->region    = (string) ($config['region']     ?? 'ap-guangzhou');
    }

    public function send(string $mobile, string $templateCode, array $vars): bool
    {
        $service   = 'sms';
        $host      = 'sms.tencentcloudapi.com';
        $action    = 'SendSms';
        $version   = '2021-01-11';
        $timestamp = time();
        $date      = gmdate('Y-m-d', $timestamp);

        // 格式化手机号（+86xxxx）
        $mobile = str_starts_with($mobile, '+') ? $mobile : '+86' . $mobile;

        $payload = json_encode([
            'SmsSdkAppId'   => $this->sdkAppId,
            'SignName'       => $this->signName,
            'TemplateId'     => $templateCode,
            'TemplateParamSet' => array_values(array_map('strval', $vars)),
            'PhoneNumberSet' => [$mobile],
        ], JSON_UNESCAPED_UNICODE);

        $hashedPayload = hash('sha256', (string) $payload);

        // 规范请求
        $canonicalHeaders = "content-type:application/json; charset=utf-8\nhost:{$host}\nx-tc-action:" . strtolower($action) . "\n";
        $signedHeaders     = 'content-type;host;x-tc-action';
        $canonicalRequest  = implode("\n", [
            'POST', '/', '', $canonicalHeaders, $signedHeaders, $hashedPayload,
        ]);

        // 待签字符串
        $credentialScope = "{$date}/{$service}/tc3_request";
        $hashedCanonical = hash('sha256', $canonicalRequest);
        $stringToSign    = "TC3-HMAC-SHA256\n{$timestamp}\n{$credentialScope}\n{$hashedCanonical}";

        // 派生签名密钥
        $secretDate    = hash_hmac('sha256', $date, 'TC3' . $this->secretKey, true);
        $secretService = hash_hmac('sha256', $service, $secretDate, true);
        $secretSigning = hash_hmac('sha256', 'tc3_request', $secretService, true);
        $signature     = hash_hmac('sha256', $stringToSign, $secretSigning);

        $authorization = "TC3-HMAC-SHA256 Credential={$this->secretId}/{$credentialScope}, "
            . "SignedHeaders={$signedHeaders}, Signature={$signature}";

        $ch = curl_init("https://{$host}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json; charset=utf-8',
                "Host: {$host}",
                "Authorization: {$authorization}",
                "X-TC-Action: {$action}",
                "X-TC-Timestamp: {$timestamp}",
                "X-TC-Version: {$version}",
                "X-TC-Region: {$this->region}",
            ],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $resp    = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr || $resp === false) {
            $this->error = 'cURL error: ' . $curlErr;
            return false;
        }

        $data = json_decode((string) $resp, true);
        $this->result = is_array($data) ? $data : ['raw' => (string) $resp];
        $result = $data['Response'] ?? [];
        if (isset($result['Error'])) {
            $this->error = ($result['Error']['Code'] ?? '') . ': ' . ($result['Error']['Message'] ?? '');
            return false;
        }

        $sendStatusSet = $result['SendStatusSet'] ?? [];
        foreach ($sendStatusSet as $status) {
            if (($status['Code'] ?? '') !== 'Ok') {
                $this->error = $status['Message'] ?? '发送失败';
                return false;
            }
        }

        return true;
    }
}
