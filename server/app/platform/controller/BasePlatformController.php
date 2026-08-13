<?php
declare(strict_types=1);

namespace app\platform\controller;

use app\common\controller\BaseLikeAdminController;
use app\platform\context\PlatformOperatorContext;

abstract class BasePlatformController extends BaseLikeAdminController
{
    protected ?PlatformOperatorContext $platformContext = null;

    protected function initialize(): void
    {
        $context = $this->request->platformContext ?? null;
        if ($context instanceof PlatformOperatorContext) {
            $this->platformContext = $context;
        }
    }
}
