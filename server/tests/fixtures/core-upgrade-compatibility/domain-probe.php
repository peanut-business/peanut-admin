<?php
declare(strict_types=1);

use app\common\contract\AdminPermissionPolicy;
use app\common\service\CoreServiceOverrides;
use app\common\service\payment\PaymentServiceFactory;
use app\common\service\payment\callback\WechatCallbackParser;
use app\common\service\payment\dto\CallbackRequest;
use app\common\service\payment\support\PaymentCrypto;
use app\common\service\permission\RegisteredAdminPermissionPolicy;

if ($argc !== 2) {
    echo "COMBINED-UPGRADE-DOMAIN-PROBE-FIXTURE-001 skipped: application root argument required\n";
    exit(0);
}

$applicationRoot = realpath($argv[1]);
if ($applicationRoot === false || !is_dir($applicationRoot)) {
    throw new RuntimeException('application root is unavailable');
}
$serverRoot = $applicationRoot . '/server';
$autoload = $serverRoot . '/vendor/autoload.php';
if (!is_file($autoload)) {
    throw new RuntimeException('application Composer autoload is unavailable');
}
require $serverRoot . '/bootstrap/environment.php';
require $autoload;

function expectCombinedDomain(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function expectCombinedDomainThrows(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (Throwable) {
        return;
    }
    throw new RuntimeException($message);
}

/** @return array{private:string,certificate:string} */
function combinedDomainCertificate(): array
{
    $privateKey = openssl_pkey_new([
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
        'private_key_bits' => 2048,
    ]);
    if ($privateKey === false) {
        throw new RuntimeException('cannot create payment probe key');
    }
    $csr = openssl_csr_new(
        ['commonName' => 'combined-upgrade.invalid'],
        $privateKey,
        ['digest_alg' => 'sha256']
    );
    $certificate = $csr === false
        ? false
        : openssl_csr_sign($csr, null, $privateKey, 1, ['digest_alg' => 'sha256'], 812);
    if ($certificate === false
        || !openssl_pkey_export($privateKey, $privatePem)
        || !openssl_x509_export($certificate, $certificatePem)) {
        throw new RuntimeException('cannot export payment probe certificate');
    }
    return ['private' => $privatePem, 'certificate' => $certificatePem];
}

$app = new think\App($serverRoot);
$app->initialize();

expectCombinedDomain(
    defined(CoreServiceOverrides::class . '::DOWNSTREAM_HOST_PROFILE')
        && CoreServiceOverrides::DOWNSTREAM_HOST_PROFILE === 'combined-upgrade-fixture',
    'downstream Host customization is unavailable'
);
$resolution = CoreServiceOverrides::registry()->resolve(CoreServiceOverrides::ADMIN_PERMISSION_POLICY);
expectCombinedDomain($resolution->key === 'authorization.permission.service.policy', 'public override key changed');
expectCombinedDomain($resolution->contract === AdminPermissionPolicy::class, 'public override contract changed');
expectCombinedDomain($resolution->contractVersion === '2.0.0', 'public override version changed');
expectCombinedDomain(
    $resolution->implementation === RegisteredAdminPermissionPolicy::class && $resolution->source === 'default',
    'public override resolution changed'
);

expectCombinedDomain(
    defined(PaymentServiceFactory::class . '::DOWNSTREAM_PAYMENT_PROFILE')
        && PaymentServiceFactory::DOWNSTREAM_PAYMENT_PROFILE === 'combined-upgrade-fixture',
    'downstream payment customization is unavailable'
);

$certificate = combinedDomainCertificate();
$apiKey = 'combined-upgrade-api-v3-key-0001';
$resourceNonce = 'fixture-iv12';
$associatedData = 'transaction';
$payment = json_encode([
    'mchid' => 'fixture-merchant',
    'appid' => 'fixture-app',
    'out_trade_no' => 'fixture-order',
    'transaction_id' => 'fixture-transaction',
    'amount' => ['total' => 1234, 'currency' => 'CNY'],
    'trade_state' => 'SUCCESS',
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$ciphertext = openssl_encrypt(
    $payment,
    'aes-256-gcm',
    $apiKey,
    OPENSSL_RAW_DATA,
    $resourceNonce,
    $tag,
    $associatedData,
    16
);
if ($ciphertext === false) {
    throw new RuntimeException('cannot encrypt payment probe resource');
}
$body = json_encode(['resource' => [
    'algorithm' => 'AEAD_AES_256_GCM',
    'ciphertext' => base64_encode($ciphertext . $tag),
    'nonce' => $resourceNonce,
    'associated_data' => $associatedData,
]], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$timestamp = (string)time();
$responseNonce = 'fixture-signature-nonce';
$signature = PaymentCrypto::sign(
    $timestamp . "\n" . $responseNonce . "\n" . $body . "\n",
    PaymentCrypto::privateKey($certificate['private'])
);
$config = [
    'wx_pay_status' => 1,
    'wx_pay_appid' => 'fixture-app',
    'wx_pay_mch_id' => 'fixture-merchant',
    'wx_pay_secret' => $apiKey,
    'wx_pay_platform_cert_path' => $certificate['certificate'],
];
$request = new CallbackRequest($body, [
    'Wechatpay-Timestamp' => $timestamp,
    'Wechatpay-Nonce' => $responseNonce,
    'Wechatpay-Serial' => PaymentCrypto::certificateSerial($certificate['certificate']),
    'Wechatpay-Signature' => $signature,
]);
$factory = new PaymentServiceFactory($config);
$parser = $factory->callback('wechat');
expectCombinedDomain($parser instanceof WechatCallbackParser, 'application payment Host changed');
$event = $parser->parse($request);
expectCombinedDomain(
    $event->channel() === 'wechat'
        && $event->orderSn() === 'fixture-order'
        && $event->transactionId() === 'fixture-transaction'
        && $event->amount() === 1234
        && $event->currency() === 'CNY'
        && $event->status() === 'success',
    'signed and encrypted payment callback behavior changed'
);

$invalidFactory = new PaymentServiceFactory(array_replace($config, [
    'wx_pay_secret' => 'combined-upgrade-api-v3-key-0002',
]));
expectCombinedDomainThrows(
    static fn() => $invalidFactory->callback('wechat')->parse($request),
    'authenticated payment ciphertext accepted the wrong key'
);

echo "COMBINED-UPGRADE-DOMAIN-PROBE-001 passed\n";
