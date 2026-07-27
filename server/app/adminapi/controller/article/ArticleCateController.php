<?php
declare(strict_types=1);

namespace app\adminapi\controller\article;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\article\ArticleCateLogic;
use app\adminapi\validate\article\ArticleCateValidate;

class ArticleCateController extends BaseAdminController
{
    public function lists() { return $this->data(ArticleCateLogic::lists()); }

    public function all()   { return $this->data(ArticleCateLogic::all()); }

    public function detail()
    {
        $this->validate($this->request->get(), ArticleCateValidate::class . '.detail');
        return $this->data(ArticleCateLogic::detail((int) $this->request->get('id')));
    }

    public function add()
    {
        $this->validate($this->request->post(), ArticleCateValidate::class . '.add');
        $r = ArticleCateLogic::add($this->request->post());
        return $r ? $this->success('操作成功') : $this->fail(ArticleCateLogic::getError());
    }

    public function edit()
    {
        $this->validate($this->request->post(), ArticleCateValidate::class . '.edit');
        $r = ArticleCateLogic::edit($this->request->post());
        return $r ? $this->success('操作成功') : $this->fail(ArticleCateLogic::getError());
    }

    public function delete()
    {
        $this->validate($this->request->post(), ArticleCateValidate::class . '.delete');
        $r = ArticleCateLogic::delete((int) $this->request->post('id'));
        return $r ? $this->success('操作成功') : $this->fail(ArticleCateLogic::getError());
    }

    public function status()
    {
        $r = ArticleCateLogic::updateStatus(
            (int) $this->request->post('id'),
            (int) $this->request->post('is_show')
        );
        return $r ? $this->success('操作成功') : $this->fail(ArticleCateLogic::getError());
    }
}
