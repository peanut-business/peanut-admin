<?php
declare(strict_types=1);

use app\adminapi\logic\notice\NoticeLogLogic;
use app\adminapi\logic\notice\NoticeSceneLogic;
use app\adminapi\validate\notice\NoticeSceneValidate;
use app\common\model\notice\NoticeLog;
use app\common\service\notice\NoticeSmsSender;
use app\common\service\notice\NoticeTenantContext;
use app\common\service\notice\NoticeTenantRepository;
use app\common\service\notice\VerificationCodeSecret;
use app\common\service\notice\VerificationCodeService;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;
use PeanutAdmin\Kernel\Context\TenantSystemContext;
use PeanutAdmin\Kernel\Persistence\Schema\KernelSchema;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function expectNoticeTenant(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function noticeTenantContext(int $tenantId, int $accountId, int $memberId, string $requestId): TenantContext
{
    return TenantContext::fromValidatedSession(new ValidatedTenantSession(
        $memberId,
        'notice-session-' . $tenantId . '-' . $memberId,
        $tenantId,
        $accountId,
        $memberId,
        'admin-web',
        new DateTimeImmutable('2031-01-01T00:00:00Z'),
        1,
    ), $requestId);
}

function noticeDatabase(PDO $admin): string
{
    $database = 'peanut_admin_notice_fresh_' . strtolower(bin2hex(random_bytes(5)));
    $admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
    return $database;
}

function noticePdo(string $host, int $port, string $password, string $database): PDO
{
    return new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        'root',
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_MULTI_STATEMENTS => true]
    );
}

function noticeFreshSchema(PDO $pdo, string $serverRoot): void
{
    foreach (KernelSchema::tableNames() as $table) {
        $pdo->exec(KernelSchema::createSql($table));
    }
    $pdo->exec(KernelSchema::addTenantMemberDepartmentForeignKeySql());
    $pdo->exec(<<<'SQL'
INSERT INTO pa_tenant
  (id, code, name, display_name, status, activated_at, created_at, updated_at)
VALUES
  (101, 'default', 'Alpha', 'Alpha', 'active', UTC_TIMESTAMP(3), UTC_TIMESTAMP(3), UTC_TIMESTAMP(3));
SQL);
    $schema = (string)file_get_contents($serverRoot . '/database/init.sql');
    expectNoticeTenant($schema !== '', 'canonical application schema is missing');
    $pdo->exec($schema);
}

final class SuccessfulNoticeSender implements NoticeSmsSender
{
    public int $calls = 0;

    public function send(
        TenantContext|TenantSystemContext $context,
        string $mobile,
        string $templateId,
        array $variables,
        ?callable $beforeSend = null
    ): array {
        $this->calls++;
        if ($beforeSend !== null) {
            $beforeSend('fixture-provider');
        }
        return ['success' => true, 'provider' => 'fixture-provider', 'error' => '', 'result' => []];
    }
}

$serverRoot = dirname(__DIR__, 2);
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = (int)(getenv('DB_PORT') ?: 3306);
$password = getenv('MYSQL_ROOT_PASSWORD') ?: 'mt02_root';
$admin = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    'root',
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$database = noticeDatabase($admin);

try {
    $pdo = noticePdo($host, $port, $password, $database);
    noticeFreshSchema($pdo, $serverRoot);
    $pdo->exec(<<<'SQL'
INSERT INTO pa_notice_template
  (id, tenant_id, name, code, channel, content, create_time, update_time)
VALUES (21, 101, 'Alpha template', 'member_notice', 1, 'Alpha ${code}', UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
INSERT INTO pa_notice_log
  (id, tenant_id, template_id, scene_id, channel, provider, receiver, title, content, status, error, extra, send_time, create_time)
VALUES (31, 101, 21, 1, 1, 'fixture-provider', '13900000000', 'Alpha', 'redacted', 2, '', '{}', UNIX_TIMESTAMP() - 600, UNIX_TIMESTAMP() - 600);
INSERT INTO pa_tenant
  (id, code, name, display_name, status, activated_at, created_at, updated_at)
VALUES (202, 'beta', 'Beta', 'Beta', 'active', UTC_TIMESTAMP(3), UTC_TIMESTAMP(3), UTC_TIMESTAMP(3));
INSERT INTO pa_notice_scene
  (id, tenant_id, code, name, variables, sms_template_id, sms_content, sms_status, create_time, update_time)
VALUES (112, 202, 'login_code', 'Beta login', JSON_ARRAY('code'), 'beta-login', 'Beta ${code}', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
INSERT INTO pa_notice_template
  (id, tenant_id, name, code, channel, content, create_time, update_time)
VALUES (121, 202, 'Beta template', 'member_notice', 1, 'Beta ${code}', UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
SQL);
    expectNoticeTenant(
        (int)$pdo->query("SELECT COUNT(*) FROM pa_notice_scene WHERE code = 'login_code'")->fetchColumn() === 2,
        'tenant-scoped scene code cannot be reused across Tenants'
    );
    expectNoticeTenant(
        (int)$pdo->query("SELECT COUNT(*) FROM pa_notice_template WHERE code = 'member_notice'")->fetchColumn() === 2,
        'tenant-scoped template key cannot be reused across Tenants'
    );
    try {
        $pdo->exec(<<<'SQL'
INSERT INTO pa_notice_template
  (tenant_id, name, code, channel, content, create_time, update_time)
VALUES (202, 'Duplicate Beta template', 'member_notice', 1, 'Beta duplicate', UNIX_TIMESTAMP(), UNIX_TIMESTAMP())
SQL);
        throw new RuntimeException('duplicate template key unexpectedly succeeded inside one Tenant');
    } catch (PDOException $exception) {
        expectNoticeTenant($exception->getCode() === '23000', 'tenant-scoped template uniqueness failed with an unexpected shape');
    }

    putenv('PHP_DB_HOST=' . $host);
    putenv('PHP_DB_PORT=' . $port);
    putenv('PHP_DB_NAME=' . $database);
    putenv('PHP_DB_USER=root');
    putenv('PHP_DB_PASS=' . $password);
    putenv('PHP_DB_PREFIX=pa_');
    $app = new think\App();
    $app->initialize();

    $alpha = noticeTenantContext(101, 1001, 501, 'fresh-notice-alpha');
    $beta = noticeTenantContext(202, 2002, 502, 'fresh-notice-beta');
    $invalidSend = new TenantSystemContext(0, NoticeTenantContext::VERIFICATION_ACTOR, 'notice.verification.send', 'invalid-send');
    $invalidVerify = new TenantSystemContext(0, NoticeTenantContext::VERIFICATION_ACTOR, 'notice.verification.verify', 'invalid-verify');

    $request = new stdClass();
    try {
        NoticeTenantContext::member($request);
        throw new RuntimeException('missing TenantContext unexpectedly succeeded');
    } catch (Throwable $exception) {
        expectNoticeTenant($exception->getMessage() !== '', 'missing TenantContext denial lost its shape');
    }

    expectNoticeTenant(NoticeSceneLogic::lists($alpha)['total'] === 4, 'Alpha scene list crossed Tenant boundary');
    expectNoticeTenant(NoticeSceneLogic::lists($beta)['total'] === 1, 'Beta scene list crossed Tenant boundary');
    expectNoticeTenant(NoticeSceneLogic::detail($alpha, 112) === [], 'cross-tenant scene detail was visible');
    expectNoticeTenant(NoticeSceneLogic::detail($alpha, 999999) === [], 'missing scene detail shape changed');
    $betaSceneBefore = $pdo->query("SELECT sms_template_id, sms_content, sms_status FROM pa_notice_scene WHERE id = 112")
        ->fetch(PDO::FETCH_ASSOC);
    expectNoticeTenant(!NoticeSceneLogic::save($alpha, [
        'id' => 112, 'sms_template_id' => 'cross-tenant', 'sms_content' => 'Code ${code}', 'sms_status' => 1,
    ]), 'cross-tenant scene save unexpectedly succeeded');
    $crossSceneError = NoticeSceneLogic::getError();
    expectNoticeTenant(!NoticeSceneLogic::save($alpha, [
        'id' => 999999, 'sms_template_id' => 'missing', 'sms_content' => 'Code ${code}', 'sms_status' => 1,
    ]), 'missing scene save unexpectedly succeeded');
    expectNoticeTenant(NoticeSceneLogic::getError() === $crossSceneError, 'scene save enumerated cross-Tenant ownership');
    expectNoticeTenant(
        $pdo->query("SELECT sms_template_id, sms_content, sms_status FROM pa_notice_scene WHERE id = 112")
            ->fetch(PDO::FETCH_ASSOC) === $betaSceneBefore,
        'cross-tenant scene save changed Beta data'
    );

    $validationErrors = [];
    foreach ([112, 999999] as $sceneId) {
        try {
            (new NoticeSceneValidate())->forTenant($alpha)->scene('detail')->failException(true)->check(['id' => $sceneId]);
            throw new RuntimeException('invalid scene validation unexpectedly succeeded');
        } catch (Throwable $exception) {
            $validationErrors[] = $exception->getMessage();
        }
    }
    expectNoticeTenant(count(array_unique($validationErrors ?? [])) === 1, 'scene validator enumerated cross-Tenant ownership');

    $beforeUntrusted = (int)$pdo->query('SELECT COUNT(*) FROM pa_notice_log')->fetchColumn();
    $sender = new SuccessfulNoticeSender();
    $service = new VerificationCodeService($sender);
    foreach (['send', 'verify'] as $operation) {
        try {
            $operation === 'send'
                ? $service->send($invalidSend, 'login_code', '13800000000')
                : $service->verify($invalidVerify, 'login_code', '13800000000', '4827');
            throw new RuntimeException("untrusted context {$operation} unexpectedly succeeded");
        } catch (Throwable) {
            expectNoticeTenant(
                (int)$pdo->query('SELECT COUNT(*) FROM pa_notice_log')->fetchColumn() === $beforeUntrusted,
                "untrusted context {$operation} wrote a notice log"
            );
        }
    }
    expectNoticeTenant($sender->calls === 0, 'untrusted Tenant context reached the provider Host');

    expectNoticeTenant($service->send($beta, 'login_code', '13800000001'), $service->getError());
    expectNoticeTenant($sender->calls === 1, 'trusted tenant send did not cross the explicit sender port once');
    expectNoticeTenant(
        (int)$pdo->query("SELECT COUNT(*) FROM pa_notice_log WHERE tenant_id = 202 AND receiver = '13800000001'")->fetchColumn() === 1,
        'tenant-owned send log was not written to Beta'
    );
    expectNoticeTenant(
        (int)$pdo->query("SELECT COUNT(*) FROM pa_notice_log WHERE tenant_id = 101 AND receiver = '13800000001'")->fetchColumn() === 0,
        'Beta send log leaked into Alpha'
    );

    $alphaScene = (int)NoticeTenantRepository::scenes($alpha)->where('code', 'login_code')->value('id');
    $betaScene = (int)NoticeTenantRepository::scenes($beta)->where('code', 'login_code')->value('id');
    $logData = static fn(int $sceneId, string $receiver, string $code): array => [
        'template_id' => 0,
        'scene_id' => $sceneId,
        'channel' => NoticeLog::CHANNEL_SMS,
        'provider' => 'fixture-provider',
        'receiver' => $receiver,
        'title' => 'Code',
        'content' => 'Code ****',
        'verify_code_hash' => VerificationCodeSecret::hash($code),
        'is_verified' => NoticeLog::VERIFIED_NO,
        'check_count' => 0,
        'verified_time' => 0,
        'status' => NoticeLog::STATUS_SUCCESS,
        'error' => '',
        'extra' => '{}',
        'send_time' => time(),
    ];
    NoticeTenantRepository::createLog($alpha, $logData($alphaScene, '13800000002', '4827'));
    NoticeTenantRepository::createLog($beta, $logData($betaScene, '13800000002', '4827'));
    NoticeTenantRepository::createLog($alpha, $logData($alphaScene, '13800000003', '5938'));

    expectNoticeTenant($service->verify($alpha, 'login_code', '13800000002', '4827'), $service->getError());
    expectNoticeTenant(
        (int)NoticeTenantRepository::logs($beta)->where('receiver', '13800000002')->value('is_verified') === NoticeLog::VERIFIED_NO,
        'Alpha verification consumed Beta code'
    );
    expectNoticeTenant($service->verify($beta, 'login_code', '13800000002', '4827'), $service->getError());

    expectNoticeTenant(!$service->verify($beta, 'login_code', '13800000003', '5938'), 'cross-tenant verification succeeded');
    $crossTenantError = $service->getError();
    expectNoticeTenant(!$service->verify($beta, 'login_code', '13800000004', '5938'), 'missing verification unexpectedly succeeded');
    expectNoticeTenant($service->getError() === $crossTenantError, 'cross-tenant verification enumerated Alpha log');

    $alphaLogs = NoticeLogLogic::lists($alpha, ['page' => 1, 'limit' => 50]);
    $betaLogs = NoticeLogLogic::lists($beta, ['page' => 1, 'limit' => 50]);
    expectNoticeTenant($alphaLogs['total'] === 3, 'Alpha admin log list crossed Tenant boundary');
    expectNoticeTenant($betaLogs['total'] === 2, 'Beta admin log list crossed Tenant boundary');
    $betaLogId = (int)NoticeTenantRepository::logs($beta)->order('id', 'desc')->value('id');
    expectNoticeTenant(NoticeLogLogic::detail($alpha, $betaLogId) === [], 'cross-tenant log detail was visible');
    expectNoticeTenant(NoticeLogLogic::detail($alpha, 999999) === [], 'missing log detail shape changed');

    echo json_encode([
        'status' => 'passed',
        'scope' => 'notice-tenant-isolation',
        'schema' => 'fresh-canonical',
        'tenant_isolation' => ['scene', 'template_key', 'send_log', 'verification', 'admin_log'],
        'provider_credentials' => 'application_host_only',
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
}
