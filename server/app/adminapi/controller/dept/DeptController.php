<?php
declare(strict_types=1);

namespace app\adminapi\controller\dept;

use app\adminapi\logic\dept\DeptLogic;

class DeptController extends AbstractOrgCrudController
{
    protected const CRUD_LOGIC = DeptLogic::class;

    public function all()
    {
        return $this->data(DeptLogic::all($this->resolveCrudContext()));
    }

    public function leaderDept()
    {
        return $this->data(DeptLogic::leaderDept($this->resolveCrudContext()));
    }
}
