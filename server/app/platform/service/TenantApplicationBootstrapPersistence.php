<?php
declare(strict_types=1);

namespace app\platform\service;

interface TenantApplicationBootstrapPersistence
{
    /** @param list<array{0:int,1:string,2:string,3:string}> $pages @param list<array{0:int,1:string,2:string}> $tabbars */
    public function seedDecoration(array $pages, array $tabbars): void;

    /** @param array<string,mixed> $tabbarSetting @param array<string,mixed> $transactionSetting */
    public function ensureSettings(array $tabbarSetting, array $transactionSetting): void;
}
