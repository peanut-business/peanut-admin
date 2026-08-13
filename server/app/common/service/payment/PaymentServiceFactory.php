<?php
declare(strict_types=1);

namespace app\common\service\payment;

use app\common\service\ConfigService;
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
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

/** Peanut 自有支付边界工厂，不承载参考系统的路由或参数兼容。 */
final class PaymentServiceFactory
{
    private array $config;
    private PaymentTransportInterface $transport;

    /**
     * 生产调用不传参数，从 pa_config 的 pay 类型读取配置并使用 cURL。
     * 验收可传入固定配置与假传输，保证不会调用真实商户。
     */
    public function __construct(?array $config = null, ?PaymentTransportInterface $transport = null)
    {
        $this->config = $config ?? ConfigService::get('pay');
        $this->transport = $transport ?? new CurlPaymentTransport();
    }

    public static function forTenant(
        TenantContext|TenantSystemContext $context,
        string $channel,
        ?PaymentTransportInterface $transport = null,
    ): self {
        $provider = match (strtolower(trim($channel))) {
            'wechat' => ExternalTenantResolver::WECHAT_PAYMENT,
            'alipay' => ExternalTenantResolver::ALIPAY_PAYMENT,
            default => throw new \RuntimeException('支付渠道不受支持'),
        };
        $binding = ExternalTenantResolver::production()->bindingForTenant($context, $provider);
        return new self($binding->config, $transport);
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
