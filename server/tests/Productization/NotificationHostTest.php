<?php
declare(strict_types=1);

use app\common\service\notice\VerificationCodeSecret;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function expectNotificationHost(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$serverRoot = dirname(__DIR__, 2);
$repositoryRoot = dirname($serverRoot);

$codeHash = VerificationCodeSecret::hash('4827');
expectNotificationHost($codeHash !== '4827', 'verification code is stored in plaintext');
expectNotificationHost(VerificationCodeSecret::matches('4827', $codeHash), 'verification code hash cannot be verified');
expectNotificationHost(!VerificationCodeSecret::matches('4828', $codeHash), 'wrong verification code is accepted');

$channelService = (string)file_get_contents(
    $serverRoot . '/app/common/service/notice/NoticeChannelService.php'
);
foreach (['new AliyunSms', 'new TencentSms', "lock(true)", 'safeReceipt', 'sanitizeError'] as $marker) {
    expectNotificationHost(str_contains($channelService, $marker), 'SMS Host invariant missing: ' . $marker);
}

$verificationService = (string)file_get_contents(
    $serverRoot . '/app/common/service/notice/VerificationCodeService.php'
);
foreach (['$this->sender->send', "['code' => '****']", 'verify_code_hash', 'NoticeTenantRepository::createLog'] as $marker) {
    expectNotificationHost(str_contains($verificationService, $marker), 'verification boundary missing: ' . $marker);
}
$applicationSender = (string)file_get_contents(
    $serverRoot . '/app/common/service/notice/ApplicationNoticeSmsSender.php'
);
expectNotificationHost(
    str_contains($applicationSender, 'NoticeChannelService::sendSms'),
    'tenant-owned notification flow does not delegate to the application credential Host'
);
expectNotificationHost(
    !str_contains($channelService, 'tenant_id'),
    'application-owned provider credential Host was tenantized'
);
expectNotificationHost(
    !str_contains($verificationService, "->where('is_verified', NoticeLog::VERIFIED_NO)"),
    'verification can fall back to an older code after the latest code is consumed'
);
expectNotificationHost(
    str_contains($verificationService, '(int)$log->is_verified === NoticeLog::VERIFIED_YES'),
    'latest successful verification record is not checked for prior consumption'
);
foreach (['ConfigService::get', 'new AliyunSms', 'new TencentSms', "'verify_code' => \$code"] as $forbidden) {
    expectNotificationHost(!str_contains($verificationService, $forbidden), 'verification service bypasses Host: ' . $forbidden);
}

$logModel = (string)file_get_contents($serverRoot . '/app/common/model/notice/NoticeLog.php');
expectNotificationHost(
    str_contains($logModel, "protected \$hidden = ['verify_code_hash', 'extra']"),
    'secret hash or provider response can be serialized'
);
$logLogic = (string)file_get_contents($serverRoot . '/app/adminapi/logic/notice/NoticeLogLogic.php');
expectNotificationHost(!str_contains($logLogic, "field('l.*"), 'notification API exposes unrestricted log columns');
expectNotificationHost(!str_contains($logLogic, 'verify_code_hash'), 'notification API selects the verification hash');
expectNotificationHost(!str_contains($logLogic, "'l.extra'"), 'notification API selects raw provider results');

$routeSource = (string)file_get_contents($serverRoot . '/route/app.php');
foreach (['notice/template/lists', 'notice/template/add', 'notice/template/edit', 'notice/template/delete'] as $route) {
    expectNotificationHost(!str_contains($routeSource, $route), 'retired generic template route remains: ' . $route);
}
foreach ([
    'app/common/service/notice/NoticeService.php',
    'app/common/service/notice/driver/mail/SmtpMail.php',
    'app/common/model/notice/NoticeTemplate.php',
    'app/adminapi/controller/notice/NoticeTemplateController.php',
    'app/adminapi/logic/notice/NoticeTemplateLogic.php',
] as $retiredPath) {
    expectNotificationHost(!is_file($serverRoot . '/' . $retiredPath), 'retired notification Runtime remains: ' . $retiredPath);
}

$migration = (string)file_get_contents(
    $serverRoot . '/database/migrations/20260811-notification-host-security.sql'
);
expectNotificationHost(
    str_contains($migration, 'CHANGE COLUMN `verify_code` `verify_code_hash` VARCHAR(255)'),
    'verification-code hash migration is missing'
);
expectNotificationHost(
    str_contains($migration, "REPLACE(`content`, `verify_code`, '****')"),
    'legacy verification-code snapshot is not redacted'
);

foreach ([
    'api-db-summary.json' => [
        'scene_management', 'send_limit', 'mobile_login_single_use', 'bind_and_change_mobile',
        'reset_password', 'expiry_and_check_count', 'log_query',
    ],
    'channel-permission-summary.json' => [
        'incomplete_provider_rejected', 'provider_switch', 'provider_disable_and_default_rule',
        'permission_granted', 'permission_revoked', 'permission_restored',
    ],
    'browser-summary.json' => [
        'four_fixed_verification_scenes', 'scene_template_settings',
        'aliyun_and_tencent_providers', 'provider_enable_state', 'send_log_query_and_columns',
    ],
] as $evidenceFile => $checks) {
    $evidence = json_decode((string)file_get_contents(
        $repositoryRoot . '/output/playwright/m01/' . $evidenceFile
    ), true, 512, JSON_THROW_ON_ERROR);
    expectNotificationHost(($evidence['ok'] ?? false) === true, 'sealed M01 evidence is not passed: ' . $evidenceFile);
    foreach ($checks as $check) {
        expectNotificationHost(
            ($evidence['checks'][$check] ?? false) === true,
            'sealed M01 evidence is missing: ' . $evidenceFile . ':' . $check
        );
    }
    expectNotificationHost(($evidence['cleanup'] ?? false) === true, 'M01 fixtures were not cleaned: ' . $evidenceFile);
}

expectNotificationHost(
    !str_contains($channelService, 'PeanutAdmin\\'),
    'application-owned channel credential Host deep imports core'
);
$tenantSources = [$verificationService, $logLogic, (string)file_get_contents(
    $serverRoot . '/app/common/service/notice/NoticeTenantRepository.php'
)];
foreach ($tenantSources as $source) {
    $withoutAllowedContextTypes = str_replace([
        'PeanutAdmin\\Kernel\\Auth\\TenantContext',
        'PeanutAdmin\\Kernel\\Context\\TenantSystemContext',
    ], '', $source);
    expectNotificationHost(
        !str_contains($withoutAllowedContextTypes, 'PeanutAdmin\\'),
        'tenant-owned notification Runtime imports core outside trusted context types'
    );
}
foreach ([$verificationService, $applicationSender] as $source) {
    foreach (['ConfigService::get', 'new AliyunSms', 'new TencentSms'] as $forbidden) {
        expectNotificationHost(!str_contains($source, $forbidden), 'tenant Runtime copied provider credential ownership');
    }
}

echo "PB07-NOTIFICATION-HOST-001 passed\n";
