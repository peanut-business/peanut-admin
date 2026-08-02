<?php
declare(strict_types=1);

namespace app\adminapi\controller\setting;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\setting\StorageLogic;
use app\adminapi\validate\setting\StorageValidate;

class StorageController extends BaseAdminController
{
    public function lists()
    {
        return $this->data(StorageLogic::lists());
    }

    public function detail()
    {
        $this->validate($this->request->get(), StorageValidate::class . '.detail');
        return $this->data(StorageLogic::detail($this->request->get()));
    }

    public function setup()
    {
        $this->validate($this->request->post(), StorageValidate::class . '.setup');
        $result = StorageLogic::setup($this->request->post());
        return $result === true ? $this->success('操作成功') : $this->success((string) $result);
    }

    public function change()
    {
        $this->validate($this->request->post(), StorageValidate::class . '.change');
        $result = StorageLogic::change($this->request->post());
        return $result ? $this->success('操作成功') : $this->fail(StorageLogic::getError());
    }
}
