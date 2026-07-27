<?php
declare(strict_types=1);

namespace app\adminapi\controller\finance;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\finance\RechargeLogic;

class RechargeController extends BaseAdminController
{
    public function lists()
    {
        $result = RechargeLogic::lists($this->request->get());
        return $this->dataLists($result['lists'], $result['count'], $result['page'], $result['limit']);
    }
}
