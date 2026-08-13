<?php
declare(strict_types=1);

namespace app\common\service\customer_service;

use app\common\model\setting\CustomerServiceSetting;
use app\common\service\FileService;
use app\common\service\file\FileTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;

final class CustomerServiceTenantRepository
{
    public static function settings(TenantContext $context)
    {
        return CustomerServiceSetting::where(
            'tenant_id',
            CustomerServiceTenantContext::tenantId($context)
        );
    }

    /** @param array<string,string> $defaults */
    public static function read(TenantContext $context, array $defaults): array
    {
        $setting = self::settings($context)->findOrEmpty();
        if ($setting->isEmpty()) {
            return $defaults;
        }
        $qrCode = '';
        if ((int)$setting->qr_file_id > 0) {
            $file = FileTenantRepository::findFile($context, (int)$setting->qr_file_id);
            if ($file !== null) {
                $qrCode = FileService::getFileUrl((string)$file->uri, (string)$file->storage);
            }
        }
        return [
            'qr_code' => $qrCode,
            'wechat' => (string)$setting->wechat,
            'phone' => (string)$setting->phone,
            'service_time' => (string)$setting->service_time,
        ];
    }

    /** @param array<string,string> $data */
    public static function save(TenantContext $context, array $data): void
    {
        unset($data['tenant_id']);
        $setting = self::settings($context)->lock(true)->findOrEmpty();
        $owned = $setting->isEmpty()
            ? ['wechat' => '', 'phone' => '', 'service_time' => '', 'qr_file_id' => null]
            : [
                'wechat' => (string)$setting->wechat,
                'phone' => (string)$setting->phone,
                'service_time' => (string)$setting->service_time,
                'qr_file_id' => (int)$setting->qr_file_id ?: null,
            ];
        foreach (['wechat', 'phone', 'service_time'] as $field) {
            if (array_key_exists($field, $data)) {
                $owned[$field] = $data[$field];
            }
        }
        if (array_key_exists('qr_code', $data)) {
            $owned['qr_file_id'] = self::ownedQrFileId($context, $data['qr_code']);
        }
        if ($setting->isEmpty()) {
            CustomerServiceSetting::create([
                'tenant_id' => CustomerServiceTenantContext::tenantId($context),
            ] + $owned);
            return;
        }
        $setting->save($owned);
    }

    private static function ownedQrFileId(TenantContext $context, string $url): ?int
    {
        $uri = FileService::setFileUrl(trim($url));
        if ($uri === '') {
            return null;
        }
        $file = FileTenantRepository::files($context)->where('uri', $uri)->findOrEmpty();
        if ($file->isEmpty()) {
            throw new \RuntimeException('客服二维码素材不存在');
        }
        return (int)$file->id;
    }
}
