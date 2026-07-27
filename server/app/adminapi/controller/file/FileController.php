<?php
declare(strict_types=1);

namespace app\adminapi\controller\file;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\file\FileCateLogic;
use app\adminapi\logic\file\FileLogic;
use app\adminapi\validate\file\FileCateValidate;

class FileController extends BaseAdminController
{
    // ---- 文件 ----
    public function lists()
    {
        $res = FileLogic::lists($this->request->get());
        return $this->dataLists($res['lists'], $res['count'], $res['pageNo'], $res['pageSize']);
    }

    public function move()
    {
        $ids = (array)$this->request->post('ids', []);
        FileLogic::move(array_map('intval', $ids), (int)$this->request->post('cid', 0));
        return $this->success('操作成功');
    }

    public function rename()
    {
        $name = trim((string)$this->request->post('name', ''));
        if ($name === '') {
            return $this->fail('名称不能为空');
        }
        FileLogic::rename((int)$this->request->post('id'), $name);
        return $this->success('操作成功');
    }

    public function delete()
    {
        $ids = (array)$this->request->post('ids', []);
        FileLogic::delete(array_map('intval', $ids));
        return $this->success('操作成功');
    }

    // ---- 分类 ----
    public function listCate()
    {
        return $this->data(FileCateLogic::lists((int)$this->request->get('type', 10)));
    }

    public function addCate()
    {
        $this->validate($this->request->post(), FileCateValidate::class . '.add');
        $r = FileCateLogic::add($this->request->post());
        return $r ? $this->success('操作成功') : $this->fail(FileCateLogic::getError());
    }

    public function editCate()
    {
        $this->validate($this->request->post(), FileCateValidate::class . '.edit');
        $r = FileCateLogic::edit($this->request->post());
        return $r ? $this->success('操作成功') : $this->fail(FileCateLogic::getError());
    }

    public function delCate()
    {
        $r = FileCateLogic::delete((int)$this->request->post('id'));
        return $r ? $this->success('操作成功') : $this->fail(FileCateLogic::getError());
    }
}
