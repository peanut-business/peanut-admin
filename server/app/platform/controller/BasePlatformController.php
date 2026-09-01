<?php
declare(strict_types=1);

namespace app\platform\controller;

use app\common\controller\BaseLikeAdminController;
use app\common\execution\CurrentExecutionContext;
use app\common\execution\ExecutionContextAccess;
use app\common\execution\PlatformExecutionContext;
use app\platform\context\PlatformOperatorContext;
use app\platform\http\PlatformRequest;
use think\App;

abstract class BasePlatformController extends BaseLikeAdminController
{
    protected ?PlatformOperatorContext $platformContext = null;
    private readonly ExecutionContextAccess $contextAccess;

    public function __construct(App $app, private readonly CurrentExecutionContext $execution)
    {
        parent::__construct($app);
        $this->contextAccess = new ExecutionContextAccess($execution);
    }

    protected function initialize(): void
    {
        $context = $this->execution->current();
        $this->platformContext = $context instanceof PlatformExecutionContext
            ? $context->platform
            : null;
    }

    protected function requestId(): string
    {
        return PlatformRequest::requestId($this->contextAccess, $this->request);
    }
}
