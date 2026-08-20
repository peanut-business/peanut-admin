<?php
declare(strict_types=1);

namespace app\adminapi\controller\dept;

use app\adminapi\logic\dept\JobsLogic;

class JobsController extends AbstractOrgCrudController
{
    protected const CRUD_LOGIC = JobsLogic::class;

    public function all()
    {
        return $this->data(JobsLogic::all($this->resolveCrudContext()));
    }
}
