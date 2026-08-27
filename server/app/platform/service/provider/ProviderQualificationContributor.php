<?php
declare(strict_types=1);

namespace app\platform\service\provider;

interface ProviderQualificationContributor
{
    /** @return list<ProviderQualificationSubject> */
    public function subjects(): array;
}
