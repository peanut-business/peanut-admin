<?php
declare(strict_types=1);

namespace app\adminapi\controller\finance;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\finance\AccountLogLogic;

class AccountLogController extends BaseAdminController
{
    public function lists()
    {
        $result = AccountLogLogic::lists($this->request->get());
        return $this->dataLists($result['lists'], $result['count'], $result['page'], $result['limit']);
    }
}
