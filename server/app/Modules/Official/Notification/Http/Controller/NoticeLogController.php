<?php
declare(strict_types=1);

namespace app\Modules\Official\Notification\Http\Controller;

use app\adminapi\controller\BaseAdminController;
use app\Modules\Official\Notification\Service\NoticeLogLogic;
use app\common\service\notice\NoticeTenantContext;

class NoticeLogController extends BaseAdminController
{
    public function lists()
    {
        return $this->data(NoticeLogLogic::lists(
            NoticeTenantContext::member($this->request),
            $this->request->get()
        ));
    }

    public function detail()
    {
        $id = (int) $this->request->get('id', 0);
        return $this->data(NoticeLogLogic::detail(NoticeTenantContext::member($this->request), $id));
    }
}
