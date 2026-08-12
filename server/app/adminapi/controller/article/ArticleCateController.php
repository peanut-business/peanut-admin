<?php
declare(strict_types=1);

namespace app\adminapi\controller\article;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\article\ArticleCateLogic;
use app\adminapi\validate\article\ArticleCateValidate;
use app\common\service\article\ArticleTenantContext;

class ArticleCateController extends BaseAdminController
{
    public function lists()
    {
        $params = $this->request->get();
        $context = ArticleTenantContext::member($this->request);
        $this->validateForTenant($context, $params, 'lists');
        $result = ArticleCateLogic::lists($context, $params);
        return $result === false ? $this->fail(ArticleCateLogic::getError()) : $this->data($result);
    }

    public function all()   { return $this->data(ArticleCateLogic::all(ArticleTenantContext::member($this->request))); }

    public function detail()
    {
        $context = ArticleTenantContext::member($this->request);
        $this->validateForTenant($context, $this->request->get(), 'detail');
        return $this->data(ArticleCateLogic::detail($context, (int) $this->request->get('id')));
    }

    public function add()
    {
        $context = ArticleTenantContext::member($this->request);
        $this->validateForTenant($context, $this->request->post(), 'add');
        $r = ArticleCateLogic::add($context, $this->request->post());
        return $r ? $this->success('添加成功') : $this->fail(ArticleCateLogic::getError());
    }

    public function edit()
    {
        $context = ArticleTenantContext::member($this->request);
        $this->validateForTenant($context, $this->request->post(), 'edit');
        $r = ArticleCateLogic::edit($context, $this->request->post());
        return $r ? $this->success('编辑成功') : $this->fail(ArticleCateLogic::getError());
    }

    public function delete()
    {
        $context = ArticleTenantContext::member($this->request);
        $this->validateForTenant($context, $this->request->post(), 'delete');
        $r = ArticleCateLogic::delete($context, (int) $this->request->post('id'));
        return $r ? $this->success('删除成功') : $this->fail(ArticleCateLogic::getError());
    }

    public function updateStatus()
    {
        $context = ArticleTenantContext::member($this->request);
        $this->validateForTenant($context, $this->request->post(), 'status');
        $r = ArticleCateLogic::updateStatus(
            $context,
            (int) $this->request->post('id'),
            (int) $this->request->post('is_show')
        );
        return $r ? $this->success('修改成功') : $this->fail(ArticleCateLogic::getError());
    }

    private function validateForTenant($context, array $data, string $scene): void
    {
        (new ArticleCateValidate())->forTenant($context)->scene($scene)->failException(true)->check($data);
    }
}
