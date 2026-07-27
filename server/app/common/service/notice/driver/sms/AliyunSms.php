<?php
declare(strict_types=1);

namespace app\common\service\notice\driver\sms;

/**
 * 阿里云短信驱动（RPC 查询字符串签名 v1.0）
 * 文档：https://help.aliyun.com/document_detail/101414.html
 *
 * 配置 key（pa_config type=notice，name=sms_aliyun，value=JSON）：
 *   access_key_id, access_key_secret, sign_name
 */
class AliyunSms extends SmsDriver
{
    private string $accessKeyId;
    private string $accessKeySecret;
    private string $signName;

    public function __construct(array $config)
    {
        $this->accessKeyId     = (string) ($config['access_key_id']     ?? '');
        $this->accessKeySecret = (string) ($config['access_key_secret'] ?? '');
        $this->signName        = (string) ($config['sign_name']         ?? '');
    }

    public function send(string $mobile, string $templateCode, array $vars): bool
    {
        $params = [
            'AccessKeyId'      => $this->accessKeyId,
            'Action'           => 'SendSms',
            'Format'           => 'JSON',
            'PhoneNumbers'     => $mobile,
            'SignName'         => $this->signName,
            'SignatureMethod'  => 'HMAC-SHA1',
            'SignatureNonce'   => uniqid('', true),
            'SignatureVersion' => '1.0',
            'TemplateCode'     => $templateCode,
            'TemplateParam'    => json_encode($vars, JSON_UNESCAPED_UNICODE),
            'Timestamp'        => gmdate('Y-m-d\TH:i:s\Z'),
            'Version'          => '2017-05-25',
        ];

        ksort($params);

        $query = '';
        foreach ($params as $k => $v) {
            $query .= '&' . rawurlencode((string) $k) . '=' . rawurlencode((string) $v);
        }
        $query = ltrim($query, '&');

        $stringToSign = 'POST&%2F&' . rawurlencode($query);
        $params['Signature'] = base64_encode(
            hash_hmac('sha1', $stringToSign, $this->accessKeySecret . '&', true)
        );

        $body = http_build_query($params);

        $ch = curl_init('https://dysmsapi.aliyuncs.com');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $resp = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr || $resp === false) {
            $this->error = 'cURL error: ' . $curlErr;
            return false;
        }

        $data = json_decode((string) $resp, true);
        if (!is_array($data) || ($data['Code'] ?? '') !== 'OK') {
            $this->error = $data['Message'] ?? $resp;
            return false;
        }

        return true;
    }
}
