<?php
declare(strict_types=1);

namespace app\platform\service\provider;

use think\facade\Db;

final readonly class ThinkPhpProviderQualificationEvidenceRepository implements ProviderQualificationEvidenceRepository
{
    public function evidenceFor(array $subjects): array
    {
        if ($subjects === []) {
            return [];
        }
        $providerKeys = array_values(array_unique(array_map(
            static fn(ProviderQualificationSubject $subject): string => $subject->providerKey,
            $subjects,
        )));
        return Db::name('provider_qualification_evidence')->whereIn('provider_key', $providerKeys)
            ->field('evidence_key,provider_key,scope_type,tenant_id,scope_reference,evidence_type,outcome,config_digest,status_code,observed_at,expires_at')
            ->order('observed_at', 'desc')->order('id', 'desc')->select()->toArray();
    }
}
