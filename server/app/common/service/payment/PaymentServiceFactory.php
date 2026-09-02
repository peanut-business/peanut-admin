<?php
declare(strict_types=1);

namespace app\common\service\payment;

use app\common\service\payment\callback\AlipayCallbackParser;
use app\common\service\payment\callback\WechatCallbackParser;
use app\common\service\payment\contract\CallbackParserInterface;
use app\common\service\payment\contract\PaymentTransportInterface;
use app\common\service\payment\contract\PrepayGatewayInterface;
use app\common\service\payment\contract\RefundGatewayInterface;
use app\common\service\payment\gateway\AlipayGateway;
use app\common\service\payment\gateway\AlipayRefundGateway;
use app\common\service\payment\gateway\WechatPayGateway;
use app\common\service\payment\gateway\WechatRefundGateway;
use app\common\service\payment\transport\CurlPaymentTransport;
use app\common\service\external\ExternalTenantResolver;

/** Peanut 自有支付边界工厂，不承载参考系统的路由或参数兼容。 */
final class PaymentServiceFactory
{
    private array $config;
    private PaymentTransportInterface $transport;

    public function __construct(
        private readonly ExternalTenantResolver $externalTenants,
        array $config = [],
        ?PaymentTransportInterface $transport = null,
    )
    {
        $this->config = $config;
        $this->transport = $transport ?? new CurlPaymentTransport();
    }

    public function forTenant(
        object $context,
        string $channel,
        ?PaymentTransportInterface $transport = null,
    ): self {
        $provider = match (strtolower(trim($channel))) {
            'wechat' => ExternalTenantResolver::WECHAT_PAYMENT,
            'alipay' => ExternalTenantResolver::ALIPAY_PAYMENT,
            default => throw new \RuntimeException('支付渠道不受支持'),
        };
        $binding = $this->externalTenants->bindingForTenant($context, $provider);
        return $this->forConfig($binding->config, $transport);
    }

    public function forConfig(array $config, ?PaymentTransportInterface $transport = null): self
    {
        return new self($this->externalTenants, $config, $transport);
    }

    public function prepay(string $channel): PrepayGatewayInterface
    {
        return match (strtolower(trim($channel))) {
            'wechat' => new WechatPayGateway($this->config, $this->transport),
            'alipay' => new AlipayGateway($this->config),
            default => throw new \RuntimeException('支付渠道不受支持'),
        };
    }

    public function callback(string $channel): CallbackParserInterface
    {
        return match (strtolower(trim($channel))) {
            'wechat' => new WechatCallbackParser($this->config),
            'alipay' => new AlipayCallbackParser($this->config),
            default => throw new \RuntimeException('支付渠道不受支持'),
        };
    }

    public function refund(string $channel): RefundGatewayInterface
    {
        return match (strtolower(trim($channel))) {
            'wechat' => new WechatRefundGateway($this->config, $this->transport),
            'alipay' => new AlipayRefundGateway($this->config, $this->transport),
            default => throw new \RuntimeException('支付渠道不受支持'),
        };
    }
}
