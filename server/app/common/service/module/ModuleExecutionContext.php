<?php
declare(strict_types=1);

namespace app\common\service\module;

/** @deprecated Compatibility alias for PeanutAdmin\Kernel\Module\ModuleExecutionContext. */
class_alias(
    \PeanutAdmin\Kernel\Module\ModuleExecutionContext::class,
    __NAMESPACE__ . '\\ModuleExecutionContext',
);
