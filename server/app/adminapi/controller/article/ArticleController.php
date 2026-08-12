<?php
declare(strict_types=1);

namespace app\adminapi\controller\article;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\article\ArticleLogic;
use app\adminapi\validate\article\ArticleValidate;
use app\common\service\article\ArticleTenantContext;

class ArticleController extends BaseAdminController
{
    public function lists()
    {
        $params = $this->request->get();
        $context = ArticleTenantContext::member($this->request);
        $this->validateForTenant($context, $params, 'lists');
        $result = ArticleLogic::lists($context, $params);
        return $result === false ? $this->fail(ArticleLogic::getError()) : $this->data($result);
    }

    public function detail()
    {
        $context = ArticleTenantContext::member($this->request);
        $this->validateForTenant($context, $this->request->get(), 'detail');
        return $this->data(ArticleLogic::detail($context, (int) $this->request->get('id')));
    }

    public function add()
    {
        $params = $this->request->post();
        $context = ArticleTenantContext::member($this->request);
        $this->validateForTenant($context, $params, 'add');
        $r = ArticleLogic::add($context, $params);
        return $r ? $this->success('添加成功') : $this->fail(ArticleLogic::getError());
    }

    public function edit()
    {
        $params = $this->request->post();
        $context = ArticleTenantContext::member($this->request);
        $this->validateForTenant($context, $params, 'edit');
        $r = ArticleLogic::edit($context, $params);
        return $r ? $this->success('编辑成功') : $this->fail(ArticleLogic::getError());
    }

    public function delete()
    {
        $context = ArticleTenantContext::member($this->request);
        $this->validateForTenant($context, $this->request->post(), 'delete');
        $r = ArticleLogic::delete($context, (int) $this->request->post('id'));
        return $r ? $this->success('删除成功') : $this->fail(ArticleLogic::getError());
    }

    public function updateStatus()
    {
        $context = ArticleTenantContext::member($this->request);
        $this->validateForTenant($context, $this->request->post(), 'status');
        $r = ArticleLogic::updateStatus(
            $context,
            (int) $this->request->post('id'),
            (int) $this->request->post('is_show')
        );
        return $r ? $this->success('修改成功') : $this->fail(ArticleLogic::getError());
    }

    private function validateForTenant($context, array $data, string $scene): void
    {
        (new ArticleValidate())->forTenant($context)->scene($scene)->failException(true)->check($data);
    }

}
