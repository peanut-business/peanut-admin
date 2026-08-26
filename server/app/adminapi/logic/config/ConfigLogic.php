<?php
declare(strict_types=1);

namespace app\adminapi\logic\config;

use app\common\logic\BaseLogic;
use app\common\service\FileService;
use app\common\service\RichTextResourceService;
use app\common\service\config\TenantApplicationSettingService;
use app\common\service\config\TenantSettingWebsiteStore;
use app\common\service\config\WebsiteConfigService;
use app\common\service\member\AuthenticatedMemberContext;
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
            fn(string $value): string => FileService::setTenantFileUrl($context, $value),
        );
    }

    public static function getCopyright(AuthenticatedMemberContext|TenantContext $context): array
    {
        $value = TenantApplicationSettingService::copyright($context)['config'] ?? [];
        if (is_array($value)) {
            return $value;
        }
        return [];
    }

    public static function saveCopyright(AuthenticatedMemberContext|TenantContext $context, array $params): bool
    {
        TenantApplicationSettingService::replaceCopyright($context, ['config' => $params['config'] ?? []]);
        return true;
    }

    public static function getAgreement(AuthenticatedMemberContext|TenantContext $context): array
    {
        $setting = TenantApplicationSettingService::agreement($context);
        return [
            'service_title' => (string)$setting['service_title'],
            'service_content' => RichTextResourceService::forRead((string)$setting['service_content']),
            'privacy_title' => (string)$setting['privacy_title'],
            'privacy_content' => RichTextResourceService::forRead((string)$setting['privacy_content']),
        ];
    }

    public static function saveAgreement(
        AuthenticatedMemberContext|TenantContext $context,
        array $params,
    ): bool
    {
        TenantApplicationSettingService::replaceAgreement($context, [
            'service_title' => trim((string)$params['service_title']),
            'service_content' => RichTextResourceService::forStorage(
                (string)$params['service_content'],
                $context,
            ),
            'privacy_title' => trim((string)$params['privacy_title']),
            'privacy_content' => RichTextResourceService::forStorage(
                (string)$params['privacy_content'],
                $context,
            ),
        ]);
        return true;
    }

    public static function getStatistics(AuthenticatedMemberContext|TenantContext $context): array
    {
        $setting = TenantApplicationSettingService::statistics($context);
        return ['clarity_code' => (string)$setting['clarity_code']];
    }

    public static function saveStatistics(
        AuthenticatedMemberContext|TenantContext $context,
        array $params,
    ): bool
    {
        TenantApplicationSettingService::replaceStatistics($context, [
            'clarity_code' => trim((string)$params['clarity_code']),
        ]);
        return true;
    }

    public static function getUser(AuthenticatedMemberContext|TenantContext $context): array
    {
        $setting = TenantApplicationSettingService::memberProfile($context);
        $avatar = trim((string)$setting['user_avatar']);
        return [
            'default_avatar' => FileService::getFileUrl(
                $avatar !== '' ? $avatar : (string)config('project.default_image.user_avatar', '')
            ),
        ];
    }

    public static function saveUser(
        AuthenticatedMemberContext|TenantContext $context,
        array $params,
    ): bool
    {
        TenantApplicationSettingService::replaceMemberProfile($context, [
            'user_avatar' => FileService::setTenantFileUrl($context, (string)$params['default_avatar']),
        ]);
        return true;
    }

    public static function getLogin(AuthenticatedMemberContext|TenantContext $context): array
    {
        $setting = TenantApplicationSettingService::login($context);
        return [
            'login_way' => $setting['login_way'],
            'coerce_mobile' => (int)$setting['coerce_mobile'],
            'login_agreement' => (int)$setting['login_agreement'],
            'third_auth' => (int)$setting['third_auth'],
            'wechat_auth' => (int)$setting['wechat_auth'],
        ];
    }

    public static function saveLogin(
        AuthenticatedMemberContext|TenantContext $context,
        array $params,
    ): bool
    {
        $loginWay = array_values(array_unique(array_map('intval', $params['login_way'])));
        sort($loginWay);
        TenantApplicationSettingService::replaceLogin($context, [
            'login_way' => $loginWay,
            'coerce_mobile' => (int)$params['coerce_mobile'],
            'login_agreement' => (int)$params['login_agreement'],
            'third_auth' => (int)$params['third_auth'],
            'wechat_auth' => (int)$params['wechat_auth'],
        ]);
        return true;
    }
}
