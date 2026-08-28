<?php
declare(strict_types=1);

namespace app\platform\service\provider;

interface ProviderQualificationEvidenceRepository
{
    /** @param array<string,mixed> $evidence */
    public function append(array $evidence): void;

    /** @param list<ProviderQualificationSubject> $subjects @return list<array<string,mixed>> */
    public function evidenceFor(array $subjects): array;
}
