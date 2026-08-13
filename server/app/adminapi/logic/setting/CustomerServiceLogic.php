<?php
declare(strict_types=1);

namespace app\adminapi\logic\setting;

use app\common\logic\BaseLogic;
use app\common\service\customer_service\CustomerServiceTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;

/**
 * Tenant-owned 客服设置。
 * 真实用户客服页继续由 Decoration Runtime 唯一消费；本设置只服务既有管理入口。
 */
class CustomerServiceLogic extends BaseLogic
{
    /** @var array<string,string> 字段白名单 => 默认值 */
    protected const FIELDS = [
        'qr_code'      => '',
        'wechat'       => '',
        'phone'        => '',
        'service_time' => '',
    ];

    public static function getConfig(TenantContext $context): array
    {
        return CustomerServiceTenantRepository::read($context, self::FIELDS);
    }

    public static function setConfig(TenantContext $context, array $params): bool
    {
        $data = [];
        foreach (self::FIELDS as $field => $default) {
            if (array_key_exists($field, $params)) {
                $data[$field] = (string) $params[$field];
            }
        }
        unset($data['tenant_id']);
        CustomerServiceTenantRepository::save($context, $data);
        return true;
    }
}
