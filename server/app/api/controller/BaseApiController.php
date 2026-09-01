<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\controller\BaseLikeAdminController;
use app\common\execution\CurrentExecutionContext;
use app\common\execution\ConsumerExecutionContext;
use think\App;

class BaseApiController extends BaseLikeAdminController
{
    protected int   $memberId   = 0;
    protected array $memberInfo = [];

    public function __construct(App $app, private readonly CurrentExecutionContext $executionContext)
    {
        parent::__construct($app);
    }

    public function initialize(): void
    {
        $current = $this->executionContext;
        if ($current->current() instanceof ConsumerExecutionContext
            && $current->current()->member !== null) {
            $this->memberId = $current->memberId();
        }
    }
}
