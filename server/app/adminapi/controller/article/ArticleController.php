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
        $result = ArticleLogic::lists($this->request->get());
        return $this->dataLists($result['lists'], $result['count'], $result['page'], $result['limit']);
    }

    public function detail()
    {
        $this->validate($this->request->get(), ArticleValidate::class . '.detail');
        return $this->data(ArticleLogic::detail((int) $this->request->get('id')));
    }

    public function add()
    {
        $this->validate($this->request->post(), ArticleValidate::class . '.add');
        $r = ArticleLogic::add($this->request->post());
        return $r ? $this->success('操作成功') : $this->fail(ArticleLogic::getError());
    }

    public function edit()
    {
        $this->validate($this->request->post(), ArticleValidate::class . '.edit');
        $r = ArticleLogic::edit($this->request->post());
        return $r ? $this->success('操作成功') : $this->fail(ArticleLogic::getError());
    }

    public function delete()
    {
        $this->validate($this->request->post(), ArticleValidate::class . '.delete');
        $r = ArticleLogic::delete((int) $this->request->post('id'));
        return $r ? $this->success('操作成功') : $this->fail(ArticleLogic::getError());
    }

    public function status()
    {
        $r = ArticleLogic::updateStatus(
            (int) $this->request->post('id'),
            (int) $this->request->post('is_show')
        );
        return $r ? $this->success('操作成功') : $this->fail(ArticleLogic::getError());
    }
}
