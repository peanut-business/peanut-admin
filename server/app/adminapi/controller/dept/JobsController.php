<?php
declare(strict_types=1);

namespace app\adminapi\controller\dept;

use think\App;
use app\common\execution\CurrentExecutionContext;

use app\adminapi\application\dept\JobsApplicationService;

class JobsController extends AbstractOrgCrudController
{
    public function __construct(App $app, CurrentExecutionContext $executionContext, private readonly JobsApplicationService $jobs)
    {
        parent::__construct($app, $executionContext, $jobs);
    }

    public function all()
    {
        return $this->data($this->jobs->all($this->resolveCrudContext()));
    }
}
