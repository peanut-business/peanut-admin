<?php
declare(strict_types=1);

namespace app\Modules\Official\Member\Http\Controller;

use app\adminapi\controller\BaseAdminController;
use app\Modules\Official\Member\Contracts\MemberAdministration;
use app\Modules\Official\Member\Validation\AccountLogValidate;
use app\common\enum\AccountLogEnum;
use app\common\execution\CurrentExecutionContext;
use think\App;

class AccountLogController extends BaseAdminController
{
    public function __construct(App $app, CurrentExecutionContext $executionContext, private readonly MemberAdministration $members)
    {
        parent::__construct($app, $executionContext);
    }

    public function lists()
    {
        $params = $this->request->get();
        $this->validate($params, AccountLogValidate::class . '.lists');
        return $this->data($this->members->balanceLogs($params));
    }

    public function getUmChangeType()
    {
        return $this->data(AccountLogEnum::getUserMoneyChangeTypeDesc());
    }
}
