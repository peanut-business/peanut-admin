<?php
declare(strict_types=1);

namespace app\common\contract\module;

use app\common\service\module\ModuleExecutionContext;

interface ModuleExecutionGuard
{
    public function assertEnabled(ModuleExecutionContext $context): void;

    public function assertScheduled(ModuleExecutionContext $context): void;

    public function assertWorker(ModuleExecutionContext $context): void;

    public function assertExternalCallback(ModuleExecutionContext $context): void;

    public function assertAdminPermission(
        ModuleExecutionContext $context,
        string $permission,
        bool $rootBypass = false,
    ): void;
}
