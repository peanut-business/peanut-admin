<?php
declare(strict_types=1);

namespace app\adminapi\controller\finance;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\finance\AccountLogLogic;
use app\adminapi\validate\finance\AccountLogValidate;
use app\common\enum\AccountLogEnum;
use app\common\service\member\MemberTenantContext;

class AccountLogController extends BaseAdminController
{
    public function lists()
    {
        $params = $this->request->get();
        $this->validate($params, AccountLogValidate::class . '.lists');
        $result = AccountLogLogic::lists(MemberTenantContext::member($this->request), $params);
        if ($result === false) {
            return $this->fail(AccountLogLogic::getError());
        }
        return $this->dataLists(
            $result['lists'],
            $result['count'],
            $result['pageNo'],
            $result['pageSize']
        );
    }

    public function getUmChangeType()
    {
        return $this->data(AccountLogEnum::getUserMoneyChangeTypeDesc());
    }
}
