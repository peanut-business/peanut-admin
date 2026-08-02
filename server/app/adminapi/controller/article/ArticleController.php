<?php
declare(strict_types=1);

namespace app\adminapi\controller\article;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\article\ArticleLogic;
use app\adminapi\validate\article\ArticleValidate;

class ArticleController extends BaseAdminController
{
    public function lists()
    {
        $params = $this->request->get();
        $this->validate($params, ArticleValidate::class . '.lists');
        $result = ArticleLogic::lists($params);
        return $result === false ? $this->fail(ArticleLogic::getError()) : $this->data($result);
    }

    public function detail()
    {
        $this->validate($this->request->get(), ArticleValidate::class . '.detail');
        return $this->data(ArticleLogic::detail((int) $this->request->get('id')));
    }

    public function add()
    {
        $params = $this->request->post();
        $this->validate($params, ArticleValidate::class . '.add');
        $r = ArticleLogic::add($params);
        return $r ? $this->success('添加成功') : $this->fail(ArticleLogic::getError());
    }

    public function edit()
    {
        $params = $this->request->post();
        $this->validate($params, ArticleValidate::class . '.edit');
        $r = ArticleLogic::edit($params);
        return $r ? $this->success('编辑成功') : $this->fail(ArticleLogic::getError());
    }

    public function delete()
    {
        $this->validate($this->request->post(), ArticleValidate::class . '.delete');
        $r = ArticleLogic::delete((int) $this->request->post('id'));
        return $r ? $this->success('删除成功') : $this->fail(ArticleLogic::getError());
    }

    public function updateStatus()
    {
        $this->validate($this->request->post(), ArticleValidate::class . '.status');
        $r = ArticleLogic::updateStatus(
            (int) $this->request->post('id'),
            (int) $this->request->post('is_show')
        );
        return $r ? $this->success('修改成功') : $this->fail(ArticleLogic::getError());
    }

}
