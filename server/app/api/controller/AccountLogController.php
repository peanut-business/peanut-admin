<?php
declare(strict_types=1);

namespace app\api\controller;

use app\api\logic\AccountLogLogic;

class AccountLogController extends BaseApiController
{
    /** 账户流水 */
    public function lists()
    {
        $params = [
            'page_no'   => $this->request->get('page_no/d', 1),
            'page_size' => $this->request->get('page_size/d', 15),
        ];

        $result = AccountLogLogic::lists($this->memberId, $params);
        return $this->dataLists($result['lists'], $result['count'], $result['page_no'], $result['page_size']);
    }
}
