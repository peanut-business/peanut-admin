<?php
declare(strict_types=1);

namespace app\platform\contract\provider;

use app\platform\value\provider\ProviderQualificationSubject;
interface ProviderQualificationEvidenceRepository
{
    /** @param list<ProviderQualificationSubject> $subjects @return list<array<string,mixed>> */
    public function evidenceFor(array $subjects): array;
}
