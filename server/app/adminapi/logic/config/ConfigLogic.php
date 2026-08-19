<?php
declare(strict_types=1);

namespace app\adminapi\logic\config;

use app\common\logic\BaseLogic;
use app\common\service\ConfigService;
use app\common\service\FileService;
use app\common\service\RichTextResourceService;
use app\common\service\config\TenantSettingWebsiteStore;
use app\common\service\config\WebsiteConfigService;
use app\common\service\member\AuthenticatedMemberContext;
use app\common\service\tenant\TenantSettingService;
use PeanutAdmin\Kernel\Auth\TenantContext;

class ConfigLogic extends BaseLogic
{
    public static function getWebsite(AuthenticatedMemberContext|TenantContext $context): array
    {
        return self::websiteService($context)->get();
    }

    public static function saveWebsite(AuthenticatedMemberContext|TenantContext $context, array $params): bool
    {
        self::websiteService($context)->save($params);
        return true;
    }

    private static function websiteService(AuthenticatedMemberContext|TenantContext $context): WebsiteConfigService
    {
        return new WebsiteConfigService(
            new TenantSettingWebsiteStore($context),
            static fn(string $value): string => FileService::getFileUrl($value),
            static fn(string $value): string => FileService::setFileUrl($value),
        );
    }

    public static function getCopyright(AuthenticatedMemberContext|TenantContext $context): array
    {
        $value = TenantSettingService::document($context, 'copyright', ['config' => []])['config'] ?? [];
        if (is_array($value)) {
            return $value;
        }
        return [];
    }

    public static function saveCopyright(AuthenticatedMemberContext|TenantContext $context, array $params): bool
    {
        TenantSettingService::replace($context, 'copyright', ['config' => $params['config'] ?? []]);
        return true;
    }

    public static function getAgreement(): array
    {
        return [
            'service_title' => (string)ConfigService::get('agreement', 'service_title', ''),
            'service_content' => RichTextResourceService::forRead((string)ConfigService::get('agreement', 'service_content', '')),
            'privacy_title' => (string)ConfigService::get('agreement', 'privacy_title', ''),
            'privacy_content' => RichTextResourceService::forRead((string)ConfigService::get('agreement', 'privacy_content', '')),
        ];
    }

    public static function saveAgreement(array $params): bool
    {
        ConfigService::setManyAtomic('agreement', [
            'service_title' => trim((string)$params['service_title']),
            'service_content' => RichTextResourceService::forStorage((string)$params['service_content']),
            'privacy_title' => trim((string)$params['privacy_title']),
            'privacy_content' => RichTextResourceService::forStorage((string)$params['privacy_content']),
        ]);
        return true;
    }

    public static function getStatistics(): array
    {
        return ['clarity_code' => (string)ConfigService::get('site_statistics', 'clarity_code', '')];
    }

    public static function saveStatistics(array $params): bool
    {
        ConfigService::setManyAtomic('site_statistics', [
            'clarity_code' => trim((string)$params['clarity_code']),
        ]);
        return true;
    }

    public static function getUser(): array
    {
        return [
            'default_avatar' => FileService::getFileUrl((string)ConfigService::get(
                'default_image',
                'user_avatar',
                (string)config('project.default_image.user_avatar', '')
            )),
        ];
    }

    public static function saveUser(array $params): bool
    {
        ConfigService::setManyAtomic('default_image', [
            'user_avatar' => FileService::setFileUrl((string)$params['default_avatar']),
        ]);
        return true;
    }

    public static function getLogin(): array
    {
        $raw = ConfigService::get('login', 'login_way', '[1,2]');
        $loginWay = is_array($raw) ? $raw : json_decode((string)$raw, true);
        if (!is_array($loginWay)) {
            $loginWay = [1, 2];
        }
        return [
            'login_way' => array_values(array_map('intval', $loginWay)),
            'coerce_mobile' => (int)ConfigService::get('login', 'coerce_mobile', 0),
            'login_agreement' => (int)ConfigService::get('login', 'login_agreement', 0),
            'third_auth' => (int)ConfigService::get('login', 'third_auth', 0),
            'wechat_auth' => (int)ConfigService::get('login', 'wechat_auth', 0),
        ];
    }

    public static function saveLogin(array $params): bool
    {
        $loginWay = array_values(array_unique(array_map('intval', $params['login_way'])));
        sort($loginWay);
        ConfigService::setManyAtomic('login', [
            'login_way' => $loginWay,
            'coerce_mobile' => (int)$params['coerce_mobile'],
            'login_agreement' => (int)$params['login_agreement'],
            'third_auth' => (int)$params['third_auth'],
            'wechat_auth' => (int)$params['wechat_auth'],
        ]);
        return true;
    }
}
