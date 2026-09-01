<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/route/registry_source.php';

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
foreach ([
    'new AliyunSms', 'new TencentSms', 'ExternalChannelBindingService::mutate', 'safeReceipt', 'sanitizeError',
    "private const BINDING_PROVIDER = 'notice.sms'",
] as $marker) {
    expectNotificationHost(str_contains($channelService, $marker), 'SMS Host invariant missing: ' . $marker);
}
expectNotificationHost(
    !str_contains($channelService, 'external_channel_binding'),
    'SMS Host bypasses the External Channel binding owner'
);
$applicationService = (string)file_get_contents(
    $serverRoot . '/app/Modules/Official/Notification/Application/NotificationApplicationService.php'
);
$notificationProvider = (string)file_get_contents(
    $serverRoot . '/app/Modules/Official/Notification/ModuleProvider.php'
);
$sceneValidator = (string)file_get_contents(
    $serverRoot . '/app/Modules/Official/Notification/Validation/NoticeSceneValidate.php'
);
$readinessHost = (string)file_get_contents(
    $serverRoot . '/app/common/service/readiness/FirstRunReadinessHost.php'
);
$readinessController = (string)file_get_contents(
    $serverRoot . '/app/adminapi/controller/config/ReadinessController.php'
);
expectNotificationHost(
    str_contains($notificationProvider, 'VerificationCodeCommands::class =>'),
    'verification command contract is not bound at startup'
);
expectNotificationHost(
    str_contains($sceneValidator, 'private readonly NotificationQueries $queries')
        && str_contains($sceneValidator, '$this->queries->sceneExists(')
        && !str_contains($sceneValidator, 'new ModuleProvider'),
    'scene validation bypasses the Notification query contract binding'
);
expectNotificationHost(
    str_contains($readinessHost, 'private readonly NotificationQueries $notifications')
        && str_contains($readinessHost, '$this->notifications->channelDetail()')
        && !str_contains($readinessHost, 'NotificationModuleProvider')
        && str_contains($readinessController, 'private readonly FirstRunReadinessHost $readiness')
        && str_contains($readinessController, '$this->readiness->checklist(')
        && !str_contains($readinessController, 'new FirstRunReadinessHost'),
    'readiness projection bypasses its container-owned Notification dependency'
);
foreach (['Login', 'OAuth', 'Sms', 'User'] as $application) {
    $consumer = (string)file_get_contents($serverRoot . '/app/api/application/' . $application . 'ApplicationService.php');
    expectNotificationHost(
        str_contains($consumer, 'VerificationCodeCommands')
            && str_contains($consumer, '$this->verificationCodes->')
            && !str_contains($consumer, '(new ModuleProvider())->verification()'),
        $application . ' consumer bypasses the Notification contract binding'
    );
}
expectNotificationHost(
    str_contains($applicationService, "NoticeLog::alias('l')")
        && !str_contains($applicationService, "where('l.tenant_id'"),
    'notification log reads do not rely on the global Tenant model scope'
);

$verificationService = (string)file_get_contents(
    $serverRoot . '/app/Modules/Official/Notification/Application/VerificationCodeService.php'
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
    str_contains($applicationSender, "getenv('APP_ENV') ?: ''")
        && str_contains($applicationSender, "'delivery' => 'simulated'"),
    'development SMS delivery is not simulated before the External Channel Host'
);
expectNotificationHost(
    str_contains($verificationService, "? '1234'")
        && str_contains($verificationService, "getenv('APP_ENV') ?: ''"),
    'development verification code is not fixed to 1234'
);
expectNotificationHost(
    !str_contains($channelService, 'ConfigService'),
    'SMS Host still reads or writes the global config table'
);
expectNotificationHost(
    str_contains($verificationService, "\$this->sender->send(\n            \$context,"),
    'verification flow does not pass its trusted Tenant context to the SMS Host'
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

$logModel = (string)file_get_contents($serverRoot . '/app/Modules/Official/Notification/Model/NoticeLog.php');
expectNotificationHost(
    str_contains($logModel, "protected \$hidden = ['verify_code_hash', 'extra']"),
    'secret hash or provider response can be serialized'
);
expectNotificationHost(!str_contains($applicationService, "field('l.*"), 'notification API exposes unrestricted log columns');
expectNotificationHost(!str_contains($applicationService, 'verify_code_hash'), 'notification API selects the verification hash');
expectNotificationHost(!str_contains($applicationService, "'l.extra'"), 'notification API selects raw provider results');

$routeSource = peanut_route_registry_source($serverRoot);
foreach (['notice/template/lists', 'notice/template/add', 'notice/template/edit', 'notice/template/delete'] as $route) {
    expectNotificationHost(!str_contains($routeSource, $route), 'retired generic template route remains: ' . $route);
}
foreach ([
    'app/common/service/notice/NoticeService.php',
    'app/common/service/notice/driver/mail/SmtpMail.php',
    'app/common/model/notice/NoticeTemplate.php',
    'app/adminapi/controller/notice/NoticeTemplateController.php',
    'app/adminapi/application/notice/NoticeTemplateLogic.php',
] as $retiredPath) {
    expectNotificationHost(!is_file($serverRoot . '/' . $retiredPath), 'retired notification Runtime remains: ' . $retiredPath);
}

$schema = (string)file_get_contents(
    $serverRoot . '/database/init.sql'
);
expectNotificationHost(
    preg_match('/`verify_code_hash`\s+varchar\(255\)/i', $schema) === 1
        && !preg_match('/`verify_code`\s+varchar/i', $schema),
    'fresh Schema does not define only the hashed verification code column'
);
expectNotificationHost(
    !str_contains($schema, 'CHANGE COLUMN `verify_code`')
        && !str_contains($schema, "REPLACE(`content`, `verify_code`, '****')"),
    'fresh Schema still contains legacy verification-code transition SQL'
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

$tenantSources = [$channelService, $verificationService, $applicationService, (string)file_get_contents(
    $serverRoot . '/app/Modules/Official/Notification/Infrastructure/Persistence/NoticeTenantRepository.php'
)];
foreach ($tenantSources as $source) {
    $withoutAllowedContextTypes = str_replace([
        'PeanutAdmin\\Kernel\\Auth\\TenantContext',
        'PeanutAdmin\\Kernel\\Context\\TenantSystemContext',
        'PeanutAdmin\\Kernel\\Persistence\\TransactionManager',
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
