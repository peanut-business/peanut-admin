<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap/environment.php';

use app\Modules\Official\Article\Application\ArticleCapabilityAuthorization;
use app\Modules\Official\Article\Infrastructure\Authorization\PdoArticleModuleAccess;
use app\common\service\capability\CrossProductAdoptionHost;
use PeanutAdmin\ArtifactRevision\Application\ArtifactRevisionService;
use PeanutAdmin\ArtifactRevision\Database\Schema as ArtifactSchema;
use PeanutAdmin\ArtifactRevision\Persistence\PdoArtifactRevisionRepository;
use PeanutAdmin\Collaboration\Application\CollaborationService;
use PeanutAdmin\Collaboration\ArtifactRevision\ArtifactRevisionCollaborationPublisher;
use PeanutAdmin\Collaboration\Contract\CollaborationPolicy;
use PeanutAdmin\Collaboration\Contract\CollaborationPolicyProvider;
use PeanutAdmin\Collaboration\Contract\CollaborationSubmission;
use PeanutAdmin\Collaboration\Contract\CollaborationSubmissionProvider;
use PeanutAdmin\Collaboration\Database\Schema as CollaborationSchema;
use PeanutAdmin\Collaboration\Persistence\PdoCollaborationRepository;
use PeanutAdmin\EntitlementQuota\Application\EntitlementQuotaService;
use PeanutAdmin\EntitlementQuota\Contract\EntitlementGrantSnapshot;
use PeanutAdmin\EntitlementQuota\Contract\EntitlementMeter;
use PeanutAdmin\EntitlementQuota\Contract\EntitlementMeterRegistry;
use PeanutAdmin\EntitlementQuota\Contract\EntitlementPolicyProvider;
use PeanutAdmin\EntitlementQuota\Database\Schema as EntitlementSchema;
use PeanutAdmin\EntitlementQuota\Persistence\PdoEntitlementQuotaRepository;
use PeanutAdmin\Kernel\Api\ApiException;
use PeanutAdmin\Kernel\Auth\Clock;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;
use PeanutAdmin\Kernel\Context\AuthorizationDecision;
use PeanutAdmin\Kernel\Context\AuthorizedOperationContext;
use PeanutAdmin\Kernel\Idempotency\IdempotencySchema;
use PeanutAdmin\Kernel\Persistence\Schema\KernelSchema;
use PeanutAdmin\Workflow\Adapter\WorkflowAssignmentResolver;
use PeanutAdmin\Workflow\Adapter\WorkflowAttachment;
use PeanutAdmin\Workflow\Adapter\WorkflowAttachmentResolver;
use PeanutAdmin\Workflow\Adapter\WorkflowAuthorizationResolver;
use PeanutAdmin\Workflow\Adapter\WorkflowSideEffectPublisher;
use PeanutAdmin\Workflow\Adapter\WorkflowSubjectRevisionResolver;
use PeanutAdmin\Workflow\Adapter\WorkflowTransitionEffects;
use PeanutAdmin\Workflow\Application\WorkflowRuntime;
use PeanutAdmin\Workflow\Database\Schema as WorkflowSchema;
use PeanutAdmin\Workflow\Package as WorkflowPackage;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require __DIR__ . '/../Support/IsolatedBackendEnvironment.php';

function expectCap06(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

final readonly class Cap06CollaborationPolicy implements CollaborationPolicyProvider
{
    public function __construct(private PDO $pdo) {}
    public function connection(): PDO { return $this->pdo; }
    public function policy(AuthorizedOperationContext $context, string $artifactType, string $artifactKey, string $capability, DateTimeImmutable $evaluatedAt): ?CollaborationPolicy
    {
        return new CollaborationPolicy(3_600, 60, 262_144, 8_388_608, 1_000, 3_600);
    }
}

final readonly class Cap06Submission implements CollaborationSubmissionProvider
{
    public function __construct(private PDO $pdo) {}
    public function connection(): PDO { return $this->pdo; }
    public function submission(AuthorizedOperationContext $context, string $artifactType, string $artifactKey, string $sessionKey, string $snapshotKey, string $snapshotSha256, int $latestSequence, DateTimeImmutable $evaluatedAt): ?CollaborationSubmission
    {
        return new CollaborationSubmission('article.body', '1', 'article:' . $artifactKey, hash('sha256', $snapshotSha256 . ':' . $latestSequence));
    }
}

final readonly class Cap06MeterRegistry implements EntitlementMeterRegistry
{
    public function find(string $meterKey, string $targetType): ?EntitlementMeter
    {
        return $meterKey === 'article.adoption' && $targetType === 'article'
            ? new EntitlementMeter($meterKey, $targetType, 'record') : null;
    }
}

final readonly class Cap06PolicyProvider implements EntitlementPolicyProvider
{
    public function snapshot(AuthorizedOperationContext $context, EntitlementMeter $meter, string $targetKey, DateTimeImmutable $evaluatedAt): ?EntitlementGrantSnapshot
    {
        return new EntitlementGrantSnapshot('grant.cap06', 'policy.cap06.1', $meter->meterKey, $meter->unitKey, 10, 'utc_month', new DateTimeImmutable('2029-01-01T00:00:00Z'), new DateTimeImmutable('2031-01-01T00:00:00Z'), 300);
    }
}

final readonly class Cap06Clock implements Clock
{
    public function now(): DateTimeImmutable { return new DateTimeImmutable('2030-02-15T12:00:00Z'); }
}

final readonly class Cap06Assignments implements WorkflowAssignmentResolver
{
    public function __construct(private PDO $pdo) {}
    public function connection(): PDO { return $this->pdo; }
    public function resolve(AuthorizedOperationContext $context, array $rules, int $initiatorMemberId, ?int $previousActorMemberId): array
    {
        return [['source_kind' => 'role', 'source_key' => 'reviewer', 'member_id' => $initiatorMemberId]];
    }
}

final readonly class Cap06WorkflowAuthorization implements WorkflowAuthorizationResolver
{
    public function __construct(private PDO $pdo) {}
    public function connection(): PDO { return $this->pdo; }
    public function authorize(AuthorizedOperationContext $trustedBasis, string $resourceKey, string $operation, array $permissionKeys, string $subjectKey): AuthorizedOperationContext
    {
        return AuthorizedOperationContext::fromDecision(AuthorizationDecision::allow($trustedBasis->tenantContext, $resourceKey, $operation, $trustedBasis->targets, hash('sha256', implode('|', $permissionKeys))));
    }
}

final readonly class Cap06Subject implements WorkflowSubjectRevisionResolver
{
    public function __construct(private PDO $pdo) {}
    public function connection(): PDO { return $this->pdo; }
    public function resolve(AuthorizedOperationContext $context, string $subjectType, string $subjectKey, string $expectedRevisionKey): array
    {
        return ['revision_key' => $expectedRevisionKey, 'sha256' => hash('sha256', $expectedRevisionKey)];
    }
}

final readonly class Cap06Attachments implements WorkflowAttachmentResolver
{
    public function __construct(private PDO $pdo) {}
    public function connection(): PDO { return $this->pdo; }
    public function snapshot(AuthorizedOperationContext $context, string $fileKey): WorkflowAttachment
    {
        throw new RuntimeException('CAP06 declares no attachments.');
    }
}

final readonly class Cap06SideEffects implements WorkflowSideEffectPublisher
{
    public function __construct(private PDO $pdo) {}
    public function connection(): PDO { return $this->pdo; }
    public function publish(PDO $pdo, AuthorizedOperationContext $context, WorkflowTransitionEffects $effects, string $parentIdempotencyKey): void {}
}

function cap06Tenant(int $tenantId, int $accountId, int $memberId, string $requestId): TenantContext
{
    return TenantContext::fromValidatedSession(new ValidatedTenantSession($accountId, '01J00000000000000000000000', $tenantId, $accountId, $memberId, 'admin-web', new DateTimeImmutable('2031-01-01T00:00:00Z'), 1), $requestId);
}

function cap06WorkflowContext(TenantContext $tenant, string $operation): AuthorizedOperationContext
{
    return AuthorizedOperationContext::fromDecision(AuthorizationDecision::allow($tenant, WorkflowPackage::DEFINITION_RESOURCE_KEY, $operation, [], hash('sha256', 'cap06:' . $operation)));
}

function cap06Graph(): array
{
    return [
        'contract_version' => 1, 'subject_resource_key' => 'article', 'subject_read_operation' => 'read', 'subject_start_operation' => 'submit',
        'start_permission_keys' => ['article.submit'],
        'nodes' => [
            ['key' => 'start', 'type' => 'start', 'completion_policy' => null, 'assignments' => []],
            ['key' => 'review', 'type' => 'review', 'completion_policy' => 'any', 'assignments' => [['kind' => 'role', 'key' => 'reviewer']]],
            ['key' => 'done', 'type' => 'terminal', 'completion_policy' => null, 'assignments' => []],
        ],
        'transitions' => [
            ['key' => 'submit', 'from' => 'start', 'to' => 'review', 'operation' => 'submit', 'action_kind' => 'advance', 'permission_keys' => ['article.submit'], 'human_required' => false, 'return_edge' => false, 'max_traversals' => null, 'notification_intent' => null, 'task_intent' => null],
            ['key' => 'approve', 'from' => 'review', 'to' => 'done', 'operation' => 'approve', 'action_kind' => 'approve', 'permission_keys' => ['article.approve'], 'human_required' => true, 'return_edge' => false, 'max_traversals' => null, 'notification_intent' => null, 'task_intent' => null],
        ],
    ];
}

$host = IsolatedBackendEnvironment::required('DB_HOST');
$port = (int)IsolatedBackendEnvironment::required('DB_PORT');
$user = IsolatedBackendEnvironment::required('DB_USER');
$password = IsolatedBackendEnvironment::required('DB_PASS');
$database = 'peanut_admin_cap06_adoption_' . strtolower(bin2hex(random_bytes(5)));
$admin = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$admin->exec("DROP DATABASE IF EXISTS `{$database}`");
$admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
    $pdo->exec('CREATE TABLE pa_tenant (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, PRIMARY KEY (id)) ENGINE=InnoDB');
    $pdo->exec("CREATE TABLE pa_tenant_member (id BIGINT UNSIGNED NOT NULL, tenant_id BIGINT UNSIGNED NOT NULL, account_id BIGINT UNSIGNED NOT NULL, status VARCHAR(16) NOT NULL, PRIMARY KEY (id), UNIQUE KEY uk_cap06_member (tenant_id, id)) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE pa_article (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, is_show TINYINT NOT NULL, delete_time BIGINT NULL, PRIMARY KEY (id)) ENGINE=InnoDB");
    $pdo->exec(IdempotencySchema::tenant());
    $pdo->exec(KernelSchema::createSql('pa_tenant_audit_event'));
    foreach ([...ArtifactSchema::createSql(), ...CollaborationSchema::createSql(), ...EntitlementSchema::createSql(), ...WorkflowSchema::createSql()] as $statement) {
        $pdo->exec($statement);
    }
    $pdo->exec('INSERT INTO pa_tenant VALUES (), ()');
    $pdo->exec("INSERT INTO pa_tenant_member VALUES (11, 1, 101, 'active'), (21, 2, 201, 'active')");
    $pdo->exec('INSERT INTO pa_article (is_show, delete_time) VALUES (1, NULL)');

    $revisions = new ArtifactRevisionService(new PdoArtifactRevisionRepository($pdo));
    $collaboration = new CollaborationService(new PdoCollaborationRepository($pdo), new Cap06CollaborationPolicy($pdo), new Cap06Submission($pdo), new ArtifactRevisionCollaborationPublisher(new PdoArtifactRevisionRepository($pdo)), static fn(): DateTimeImmutable => new DateTimeImmutable('2030-02-15T12:00:00Z'));
    $quota = new EntitlementQuotaService(new PdoEntitlementQuotaRepository($pdo), new Cap06MeterRegistry(), new Cap06PolicyProvider(), new Cap06Clock());
    $workflow = new WorkflowRuntime($pdo, new Cap06Assignments($pdo), new Cap06WorkflowAuthorization($pdo), new Cap06Subject($pdo), new Cap06Attachments($pdo), new Cap06SideEffects($pdo));
    $tenant = cap06Tenant(1, 101, 11, 'cap06-positive');
    $draft = $workflow->saveDraft(cap06WorkflowContext($tenant, 'write'), 'peanut.article', 'article.approval', cap06Graph(), null, 'cap06-definition-draft');
    $workflow->publishDefinition(cap06WorkflowContext($tenant, 'publish'), 'peanut.article', 'article.approval', 1, 'cap06-definition-publish');

    $permittedArticleKeys = ['1'];
    $authorization = new ArticleCapabilityAuthorization(
        new PdoArticleModuleAccess($pdo),
        new class implements \app\Modules\Official\Article\Contracts\ArticleQueries {
            public function visible(TenantContext $context, int $articleId): bool
            {
                return $context->tenantId === 1 && $articleId === 1;
            }

            public function options(TenantContext $context, int $limit): array
            {
                return [];
            }
        },
        static function (TenantContext $trustedTenant, string $operation, string $articleKey) use (&$permittedArticleKeys): bool {
            return in_array($articleKey, $permittedArticleKeys, true)
                && $trustedTenant->tenantId === 1
                && $operation === 'write';
        },
    );
    $adapter = new CrossProductAdoptionHost($revisions, $collaboration, $quota, $workflow);
    $receipt = $adapter->adopt($authorization->authorizedContext($tenant, '1', 'write'), '1');
    expectCap06($receipt['workflow_status'] === 'completed', 'positive adoption did not complete');

    $tables = ['pa_artifact', 'pa_artifact_revision', 'pa_collaboration_session', 'pa_entitlement_reservation', 'pa_workflow_instance', 'pa_tenant_audit_event', 'pa_tenant_idempotency_record'];
    $counts = static function () use ($pdo, $tables): array {
        $result = [];
        foreach ($tables as $table) { $result[$table] = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn(); }
        return $result;
    };
    $before = $counts();
    foreach ([['1', []], ['999', ['999']]] as [$deniedArticleKey, $permittedKeys]) {
        $permittedArticleKeys = $permittedKeys;
        try {
            $adapter->adopt(
                $authorization->authorizedContext($tenant, $deniedArticleKey, 'write'),
                $deniedArticleKey,
            );
            throw new RuntimeException('denied adoption unexpectedly succeeded');
        } catch (ApiException $exception) {
            expectCap06([$exception->errorCode, $exception->httpStatus, $exception->getMessage()] === ['ARTICLE_CAPABILITY_DENIED', 404, 'Article capability is unavailable.'], 'denial shape enumerated the reason');
        }
        expectCap06($counts() === $before, 'denied adoption produced partial writes');
    }

    echo json_encode(['status' => 'passed', 'scope' => 'single-default-tenant-sequential-adoption', 'positive' => ['article_key' => $receipt['article_key'], 'workflow_status' => $receipt['workflow_status']], 'denials' => ['permission' => 1, 'invisible_or_missing' => 1], 'core_writes_before_denial' => false, 'cross_tenant_article_isolation_claimed' => false, 'global_transaction_claimed' => false], JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
}
