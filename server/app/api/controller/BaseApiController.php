<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\controller\BaseLikeAdminController;
use app\common\execution\CurrentExecutionContext;
use app\common\execution\ConsumerExecutionContext;

class BaseApiController extends BaseLikeAdminController
{
    protected int   $memberId   = 0;
    protected array $memberInfo = [];

    public function initialize(): void
    {
        $current = app(CurrentExecutionContext::class);
        if ($current->current() instanceof ConsumerExecutionContext
            && $current->current()->member !== null) {
            $this->memberId = $current->memberId();
        }
    }
}
