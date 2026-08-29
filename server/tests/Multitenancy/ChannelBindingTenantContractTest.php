<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function expectChannelBindingTenant(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$serverRoot = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string)file_get_contents($serverRoot . '/' . $path);

$noticeController = $read('app/Modules/Official/Notification/Http/Controller/NoticeChannelController.php');
$noticeService = $read('app/common/service/notice/NoticeChannelService.php');
$sender = $read('app/common/service/notice/ApplicationNoticeSmsSender.php');
$verification = $read('app/common/service/notice/VerificationCodeService.php');
$menuController = $read('app/Modules/Official/Oauth/Http/Controller/OfficialAccountMenuController.php');
$menuLogic = $read('app/Modules/Official/Oauth/Application/OfficialAccountMenuApplicationService.php');

foreach ([$noticeController, $menuController] as $controller) {
    expectChannelBindingTenant(
        str_contains($controller, 'MemberTenantContext::member()'),
        'admin controller does not inject its trusted Tenant context'
    );
}

foreach ([
    "private const BINDING_PROVIDER = 'notice.sms'",
    'ExternalChannelBindingService::mutate',
    "'sms_default'",
    "'sms_aliyun'",
    "'sms_tencent'",
] as $marker) {
    expectChannelBindingTenant(str_contains($noticeService, $marker), 'Tenant SMS binding invariant missing: ' . $marker);
}
expectChannelBindingTenant(
    !str_contains($noticeService, 'external_channel_binding')
        && !str_contains($noticeService, "Db::name('external_channel_binding')"),
    'NoticeChannelService still accesses the shared external binding table directly'
);
expectChannelBindingTenant(
    !str_contains($noticeService, 'ConfigService'),
    'Tenant SMS configuration falls back to global pa_config'
);
expectChannelBindingTenant(
    str_contains($sender, 'NoticeChannelService::sendSms($context,'),
    'application sender drops the trusted Tenant context'
);
expectChannelBindingTenant(
    str_contains($verification, "\$this->sender->send(\n            \$context,"),
    'verification sender call does not preserve the trusted Tenant context'
);

foreach (['ExternalChannelBindingService::config', 'ExternalChannelBindingService::update',
    'ExternalTenantResolver::WECHAT_OFFICIAL_CALLBACK'] as $marker) {
    expectChannelBindingTenant(str_contains($menuLogic, $marker), 'official-account menu binding invariant missing: ' . $marker);
}
expectChannelBindingTenant(
    str_contains($menuLogic, "(string)(\$config['app_id'] ?? '')")
        && str_contains($menuLogic, "(string)(\$config['app_secret'] ?? '')"),
    'official-account menu publish does not use the current Tenant binding credentials'
);
expectChannelBindingTenant(
    str_contains($menuLogic, "\$config['menu'] = \$menu") && !str_contains($menuLogic, 'ConfigService'),
    'official-account menu is not merged into the Tenant binding'
);

echo "CHANNEL-BINDING-TENANT-001 passed\n";
