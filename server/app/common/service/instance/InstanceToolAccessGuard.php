<?php
declare(strict_types=1);

namespace app\common\service\instance;

final class InstanceToolAccessGuard
{
    private function __construct(private readonly ?DeploymentMode $mode)
    {
    }

    public static function fromConfiguredValue(mixed $value): self
    {
        return new self(DeploymentMode::fromConfiguredValue($value));
    }

    public function allows(): bool
    {
        return $this->mode === DeploymentMode::Standalone;
    }
}
