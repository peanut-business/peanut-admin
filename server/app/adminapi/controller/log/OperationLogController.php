<?php
declare(strict_types=1);

namespace app\adminapi\controller\log;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\log\OperationLogLogic;

class OperationLogController extends BaseAdminController
{
    public function lists()
    {
        $res = OperationLogLogic::lists($this->request->get());
        return $this->dataLists($res['lists'], $res['count'], $res['pageNo'], $res['pageSize']);
    }

    public function clear()
    {
        OperationLogLogic::clear();
        return $this->success('操作成功');
    }
}
