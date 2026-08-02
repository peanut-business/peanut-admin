<?php
declare(strict_types=1);

namespace app\adminapi\controller\notice;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\notice\NoticeSceneLogic;
use app\adminapi\validate\notice\NoticeSceneValidate;

class NoticeSceneController extends BaseAdminController
{
    public function lists()
    {
        return $this->data(NoticeSceneLogic::lists());
    }

    public function detail()
    {
        $params = $this->request->get();
        $this->validate($params, NoticeSceneValidate::class . '.detail');
        return $this->data(NoticeSceneLogic::detail((int) $params['id']));
    }

    public function save()
    {
        $params = $this->request->post();
        $this->validate($params, NoticeSceneValidate::class . '.save');
        $result = NoticeSceneLogic::save($params);
        return $result
            ? $this->success('保存成功')
            : $this->fail(NoticeSceneLogic::getError());
    }
}
