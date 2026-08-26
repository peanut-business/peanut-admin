<?php
declare(strict_types=1);

namespace app\Modules\Official\Notification\Http\Controller;

use app\adminapi\controller\BaseAdminController;
use app\Modules\Official\Notification\Service\NoticeSceneLogic;
use app\Modules\Official\Notification\Validation\NoticeSceneValidate;
use app\common\service\notice\NoticeTenantContext;
use PeanutAdmin\Kernel\Auth\TenantContext;

class NoticeSceneController extends BaseAdminController
{
    public function lists()
    {
        return $this->data(NoticeSceneLogic::lists(NoticeTenantContext::member($this->request)));
    }

    public function detail()
    {
        $params = $this->request->get();
        $context = NoticeTenantContext::member($this->request);
        $this->validateForTenant($context, $params, 'detail');
        return $this->data(NoticeSceneLogic::detail($context, (int) $params['id']));
    }

    public function save()
    {
        $params = $this->request->post();
        $context = NoticeTenantContext::member($this->request);
        $this->validateForTenant($context, $params, 'save');
        $result = NoticeSceneLogic::save($context, $params);
        return $result
            ? $this->success('保存成功')
            : $this->fail(NoticeSceneLogic::getError());
    }

    private function validateForTenant(TenantContext $context, array $data, string $scene): void
    {
        (new NoticeSceneValidate())->forTenant($context)->scene($scene)->failException(true)->check($data);
    }
}
