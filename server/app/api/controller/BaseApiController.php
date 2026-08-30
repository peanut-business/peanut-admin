<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\controller\BaseLikeAdminController;
use app\common\execution\CurrentExecutionContext;
use app\common\execution\ExecutionContext;

class BaseApiController extends BaseLikeAdminController
{
    protected int   $memberId   = 0;
    protected array $memberInfo = [];

    public function initialize(): void
    {
        $current = app(CurrentExecutionContext::class);
        if ($current->current()?->actorType === ExecutionContext::MEMBER) {
            $this->memberId = $current->memberId();
        }
    }
}
