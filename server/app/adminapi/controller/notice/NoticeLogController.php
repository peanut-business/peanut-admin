<?php
declare(strict_types=1);

namespace app\adminapi\controller\notice;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\notice\NoticeLogLogic;

class NoticeLogController extends BaseAdminController
{
    public function lists()
    {
        return $this->data(NoticeLogLogic::lists($this->request->get()));
    }

    public function detail()
    {
        $id = (int) $this->request->get('id', 0);
        return $this->data(NoticeLogLogic::detail($id));
    }
}
