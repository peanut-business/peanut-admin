<?php
declare(strict_types=1);

namespace app\platform\controller;

use app\common\controller\BaseLikeAdminController;
use app\common\execution\CurrentExecutionContext;
use app\platform\context\PlatformOperatorContext;

abstract class BasePlatformController extends BaseLikeAdminController
{
    protected ?PlatformOperatorContext $platformContext = null;

    protected function initialize(): void
    {
        $context = app(CurrentExecutionContext::class)->current();
        $this->platformContext = $context?->scope instanceof PlatformOperatorContext
            ? $context->scope
            : null;
    }
}
