<?php
declare(strict_types=1);

namespace app\common\service\capability;

use PeanutAdmin\ArtifactRevision\Application\ArtifactRevisionService;
use PeanutAdmin\Collaboration\Application\CollaborationService;
use PeanutAdmin\EntitlementQuota\Application\EntitlementQuotaService;
use PeanutAdmin\Kernel\Context\AuthorizationDecision;
use PeanutAdmin\Kernel\Context\AuthorizedOperationContext;
use PeanutAdmin\Workflow\Application\WorkflowRuntime;
use Throwable;

/** Thin CAP06 composition over the four Alpha.5 public runtimes. */
final class CrossProductAdoptionHost
{
    private const ARTIFACT_TYPE = ArticleCapabilityAuthorization::RESOURCE_KEY;
    private const ENGINE_NAME = 'peanut.article';
    private const ENGINE_VERSION = 'v1.0.0';
    private const METER_KEY = 'article.adoption';
    private const MODULE_KEY = 'peanut.article';
    private const WORKFLOW_KEY = 'article.approval';

    public function __construct(
        private readonly ArtifactRevisionService $revisions,
        private readonly CollaborationService $collaboration,
        private readonly EntitlementQuotaService $quota,
        private readonly WorkflowRuntime $workflow,
    ) {}

    /** @return array<string, mixed> */
    public function adopt(AuthorizedOperationContext $context, string $articleKey): array
    {
        $reservationKey = null;
        $quotaCommitted = false;
        try {
            $this->assertArticleContext($context, $articleKey);
            $digest = hash('sha256', implode("\0", [
                (string) $context->tenantContext->tenantId,
                $articleKey,
                $context->authorizationBasisDigest,
            ]));
            $created = $this->revisions->createRevision(
                $context,
                self::ARTIFACT_TYPE,
                $articleKey,
                null,
                null,
                'cap06-revision-' . $digest,
            );
            $base = $this->revisions->finalizeRevision(
                $context,
                self::ARTIFACT_TYPE,
                $articleKey,
                $created->revisionKey,
                $created->artifactRevision,
                $created->revision,
                'peanut.article',
                '1.0.0',
                'article:' . $articleKey,
                hash('sha256', 'article:' . $articleKey),
                null,
                'cap06-finalize-' . $digest,
            );
            if ($base->canonicalEnvelopeSha256 === null) {
                throw ArticleCapabilityAuthorization::denied();
            }

            $opened = $this->collaboration->openSession(
                $this->operation($context, 'write'),
                self::ARTIFACT_TYPE,
                $articleKey,
                self::ENGINE_NAME,
                self::ENGINE_VERSION,
                $base->revisionKey,
                $base->canonicalEnvelopeSha256,
                'cap06-collaboration-' . $digest,
            );
            $joined = $this->collaboration->joinSession(
                $this->operation($context, 'write'),
                $opened->sessionKey,
                $context->tenantContext->clientKey,
                'write',
                'cap06-collaboration-join-' . $digest,
            );
            if ($joined->leaseKey === null) {
                throw ArticleCapabilityAuthorization::denied();
            }
            $snapshot = 'article:' . $articleKey;
            $stateVector = 'article-state:' . $articleKey;
            $this->collaboration->saveSnapshot(
                $this->operation($context, 'write'),
                $opened->sessionKey,
                $joined->leaseKey,
                0,
                $snapshot,
                hash('sha256', $snapshot),
                $stateVector,
                hash('sha256', $stateVector),
                'cap06-collaboration-snapshot-' . $digest,
            );
            $published = $this->collaboration->publish(
                $this->operation($context, 'publish'),
                $opened->sessionKey,
                'cap06-publish-' . $digest,
            );
            if ($published->publishedRevisionKey === null || $published->publishedRevisionSha256 === null) {
                throw ArticleCapabilityAuthorization::denied();
            }

            $reserved = $this->quota->reserve(
                $context,
                self::METER_KEY,
                self::ARTIFACT_TYPE,
                $articleKey,
                1,
                'cap06-quota-reserve-' . $digest,
            );
            $reservationKey = $reserved->reservationKey;
            $started = $this->workflow->startInstance(
                $context,
                self::MODULE_KEY,
                self::WORKFLOW_KEY,
                self::ARTIFACT_TYPE,
                $articleKey,
                $published->publishedRevisionKey,
                [],
                'cap06-workflow-' . $digest,
            );
            if ($started->instanceKey === null
                || $started->instanceRevision === null
                || $started->instanceStatus === null) {
                throw ArticleCapabilityAuthorization::denied();
            }
            $approved = $started->instanceStatus === 'completed'
                ? $started
                : $this->workflow->applyTransition(
                    $context,
                    $started->instanceKey,
                    'approve',
                    $started->instanceRevision,
                    $published->publishedRevisionKey,
                    null,
                    [],
                    'cap06-workflow-approve-' . $digest,
                );
            $committed = $this->quota->commit(
                $context,
                self::METER_KEY,
                self::ARTIFACT_TYPE,
                $articleKey,
                $reservationKey,
                'cap06-quota-commit-' . $digest,
            );
            $quotaCommitted = true;

            return [
                'article_key' => $articleKey,
                'revision_key' => $published->publishedRevisionKey,
                'revision_sha256' => $published->publishedRevisionSha256,
                'collaboration_session_key' => $published->sessionKey,
                'quota_reservation_key' => $committed->reservationKey,
                'workflow_instance_key' => $approved->instanceKey,
                'workflow_status' => $approved->instanceStatus,
            ];
        } catch (Throwable) {
            $compensationFailed = false;
            if ($reservationKey !== null && !$quotaCommitted) {
                try {
                    $this->quota->release(
                        $context,
                        self::METER_KEY,
                        self::ARTIFACT_TYPE,
                        $articleKey,
                        $reservationKey,
                        'cap06-quota-release-' . hash('sha256', $reservationKey),
                    );
                } catch (Throwable) {
                    $compensationFailed = true;
                }
            }
            if ($compensationFailed) {
                throw new \PeanutAdmin\Kernel\Api\ApiException(
                    'ARTICLE_CAPABILITY_COMPENSATION_FAILED',
                    500,
                    'Article capability is unavailable.',
                );
            }
            throw ArticleCapabilityAuthorization::denied();
        }
    }

    private function operation(AuthorizedOperationContext $context, string $operation): AuthorizedOperationContext
    {
        return AuthorizedOperationContext::fromDecision(AuthorizationDecision::allow(
            $context->tenantContext,
            $context->resourceKey,
            $operation,
            $context->targets,
            $context->authorizationBasisDigest,
        ));
    }

    private function assertArticleContext(AuthorizedOperationContext $context, string $articleKey): void
    {
        $targets = array_values($context->targets);
        $target = $targets[0] ?? null;
        if ($context->tenantContext->tenantId < 1
            || $context->tenantContext->accountId < 1
            || $context->tenantContext->memberId < 1
            || $context->tenantContext->requestId === ''
            || preg_match('/^[0-9a-f]{64}$/D', $context->authorizationBasisDigest) !== 1
            || preg_match('/^[1-9][0-9]*$/D', $articleKey) !== 1
            || !hash_equals(self::ARTIFACT_TYPE, $context->resourceKey)
            || count($targets) !== 1
            || $target === null
            || $target->targetRole !== 'primary'
            || !hash_equals(self::ARTIFACT_TYPE, $target->targetResourceKey)
            || count($target->targetIds) !== 1
            || !hash_equals($articleKey, $target->targetIds[0])) {
            throw ArticleCapabilityAuthorization::denied();
        }
    }
}
