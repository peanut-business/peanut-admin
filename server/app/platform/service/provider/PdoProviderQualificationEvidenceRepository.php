<?php
declare(strict_types=1);

namespace app\platform\service\provider;

use PDO;

final readonly class PdoProviderQualificationEvidenceRepository implements ProviderQualificationEvidenceRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function append(array $evidence): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_provider_qualification_evidence
  (evidence_key,provider_key,scope_type,tenant_id,scope_reference,evidence_type,outcome,
   config_digest,status_code,request_id,observed_at,expires_at,recorded_at)
VALUES
  (:evidence_key,:provider_key,:scope_type,:tenant_id,:scope_reference,:evidence_type,:outcome,
   :config_digest,:status_code,:request_id,:observed_at,:expires_at,:recorded_at)
SQL);
        $statement->execute($evidence);
    }

    public function evidenceFor(array $subjects): array
    {
        if ($subjects === []) {
            return [];
        }
        $providerKeys = array_values(array_unique(array_map(
            static fn(ProviderQualificationSubject $subject): string => $subject->providerKey,
            $subjects,
        )));
        $placeholders = implode(',', array_fill(0, count($providerKeys), '?'));
        $statement = $this->pdo->prepare(<<<SQL
SELECT evidence_key,provider_key,scope_type,tenant_id,scope_reference,evidence_type,outcome,
       config_digest,status_code,observed_at,expires_at
FROM pa_provider_qualification_evidence
WHERE provider_key IN ({$placeholders})
ORDER BY observed_at DESC,id DESC
SQL);
        $statement->execute($providerKeys);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
