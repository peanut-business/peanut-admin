<?php
declare(strict_types=1);

namespace app\common\service\config;

use app\common\service\member\AuthenticatedMemberContext;
use app\common\service\tenant\TenantSettingService;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

/** Typed access to Tenant-owned public application settings. */
final class TenantApplicationSettingService
{
    private const AGREEMENT = 'agreement';
    private const STATISTICS = 'site-statistics';
    private const MEMBER_PROFILE = 'member-profile';
    private const LOGIN = 'login';
    private const WEB_PAGE = 'web-page';
    private const HOT_SEARCH = 'hot-search';

    public static function agreement(AuthenticatedMemberContext|TenantContext|TenantSystemContext $context): array
    {
        return self::document($context, self::AGREEMENT, [
            'service_title' => '',
            'service_content' => '',
            'privacy_title' => '',
            'privacy_content' => '',
        ]);
    }

    public static function replaceAgreement(AuthenticatedMemberContext|TenantContext|TenantSystemContext $context, array $document): void
    {
        TenantSettingService::replace($context, self::AGREEMENT, $document);
    }

    public static function statistics(AuthenticatedMemberContext|TenantContext|TenantSystemContext $context): array
    {
        return self::document($context, self::STATISTICS, ['clarity_code' => '']);
    }

    public static function replaceStatistics(AuthenticatedMemberContext|TenantContext|TenantSystemContext $context, array $document): void
    {
        TenantSettingService::replace($context, self::STATISTICS, $document);
    }

    public static function memberProfile(AuthenticatedMemberContext|TenantContext|TenantSystemContext $context): array
    {
        return self::document($context, self::MEMBER_PROFILE, ['user_avatar' => '']);
    }

    public static function replaceMemberProfile(AuthenticatedMemberContext|TenantContext|TenantSystemContext $context, array $document): void
    {
        TenantSettingService::replace($context, self::MEMBER_PROFILE, $document);
    }

    public static function login(AuthenticatedMemberContext|TenantContext|TenantSystemContext $context): array
    {
        $document = self::document($context, self::LOGIN, [
            'login_way' => [1, 2],
            'coerce_mobile' => 0,
            'login_agreement' => 0,
            'third_auth' => 0,
            'wechat_auth' => 0,
        ]);
        $loginWay = is_array($document['login_way'] ?? null)
            ? array_values(array_unique(array_map('intval', $document['login_way'])))
            : [1, 2];
        $document['login_way'] = array_values(array_intersect($loginWay, [1, 2]));
        return $document;
    }

    public static function replaceLogin(AuthenticatedMemberContext|TenantContext|TenantSystemContext $context, array $document): void
    {
        TenantSettingService::replace($context, self::LOGIN, $document);
    }

    public static function webPage(AuthenticatedMemberContext|TenantContext|TenantSystemContext $context): array
    {
        return self::document($context, self::WEB_PAGE, [
            'status' => 1,
            'page_status' => 0,
            'page_url' => '',
        ]);
    }

    public static function replaceWebPage(AuthenticatedMemberContext|TenantContext|TenantSystemContext $context, array $document): void
    {
        TenantSettingService::replace($context, self::WEB_PAGE, $document);
    }

    public static function hotSearch(AuthenticatedMemberContext|TenantContext|TenantSystemContext $context): array
    {
        return self::document($context, self::HOT_SEARCH, ['status' => 0]);
    }

    public static function replaceHotSearch(AuthenticatedMemberContext|TenantContext|TenantSystemContext $context, array $document): void
    {
        TenantSettingService::replace($context, self::HOT_SEARCH, $document);
    }

    private static function document(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        string $namespace,
        array $defaults,
    ): array {
        return array_replace(
            $defaults,
            TenantSettingService::document($context, $namespace),
        );
    }

    private function __construct()
    {
    }
}
