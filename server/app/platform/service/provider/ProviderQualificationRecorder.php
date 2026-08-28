<?php
declare(strict_types=1);

namespace app\platform\service\provider;

use DateTimeImmutable;
use InvalidArgumentException;

/** Internal write boundary. HTTP exposes no generic qualification write route. */
final readonly class ProviderQualificationRecorder
{
    public function __construct(private ProviderQualificationEvidenceRepository $repository)
    {
    }

    public function record(
        ProviderQualificationSubject $subject,
        string $evidenceType,
        string $outcome,
        string $statusCode,
        string $requestId,
        DateTimeImmutable $observedAt,
        DateTimeImmutable $expiresAt,
    ): string {
        if (!in_array($evidenceType, ['connectivity', 'callback', 'production'], true)
            || !in_array($outcome, ['passed', 'failed'], true)
            || preg_match('/^[A-Z][A-Z0-9_]{2,95}$/D', $statusCode) !== 1
            || trim($requestId) === '' || strlen($requestId) > 128
            || $expiresAt <= $observedAt
        ) {
            throw new InvalidArgumentException('PROVIDER_QUALIFICATION_EVIDENCE_INVALID');
        }
        $key = 'pqe_' . bin2hex(random_bytes(16));
        $this->repository->append([
            'evidence_key' => $key,
            'provider_key' => $subject->providerKey,
            'scope_type' => $subject->scopeType,
            'tenant_id' => $subject->tenantId,
            'scope_reference' => $subject->scopeReference,
            'evidence_type' => $evidenceType,
            'outcome' => $outcome,
            'config_digest' => $subject->configDigest,
            'status_code' => $statusCode,
            'request_id' => $requestId,
            'observed_at' => $observedAt->format('Y-m-d H:i:s.u'),
            'expires_at' => $expiresAt->format('Y-m-d H:i:s.u'),
            'recorded_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s.u'),
        ]);
        return $key;
    }
}
