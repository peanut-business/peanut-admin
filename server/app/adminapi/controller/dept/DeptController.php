<?php
declare(strict_types=1);

namespace app\adminapi\controller\dept;

use think\App;
use app\common\execution\CurrentExecutionContext;

use app\adminapi\application\dept\DeptApplicationService;

class DeptController extends AbstractOrgCrudController
{
    public function __construct(App $app, CurrentExecutionContext $executionContext, private readonly DeptApplicationService $departments)
    {
        parent::__construct($app, $executionContext);
    }

    protected const CRUD_SERVICE = DeptApplicationService::class;

    public function all()
    {
        return $this->data($this->departments->all($this->resolveCrudContext()));
    }

    public function leaderDept()
    {
        return $this->data($this->departments->leaderDept($this->resolveCrudContext()));
    }
}
