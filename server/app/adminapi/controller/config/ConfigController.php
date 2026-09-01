<?php
declare(strict_types=1);

namespace app\adminapi\controller\config;

use think\App;
use app\common\execution\CurrentExecutionContext;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\application\config\ConfigApplicationService;
use app\adminapi\validate\config\WebsiteValidate;
use app\common\service\member\MemberTenantContext;
use InvalidArgumentException;

class ConfigController extends BaseAdminController
{
    public function __construct(App $app, CurrentExecutionContext $executionContext, private readonly ConfigApplicationService $configuration)
    {
        parent::__construct($app, $executionContext);
    }

    public function getWebsite()
    {
        return $this->data($this->configuration->getWebsite(MemberTenantContext::member()));
    }

    public function saveWebsite()
    {
        try {
            $this->configuration->saveWebsite(
                MemberTenantContext::member(),
                $this->request->post()
            );
        } catch (InvalidArgumentException $exception) {
            return $this->fail($exception->getMessage());
        }
        return $this->success('操作成功');
    }

    public function getCopyright() { return $this->data($this->configuration->getCopyright(MemberTenantContext::member())); }
    public function saveCopyright() { return $this->save('copyright', 'saveCopyright'); }
    public function getAgreement() { return $this->data($this->configuration->getAgreement(MemberTenantContext::member())); }
    public function saveAgreement() { return $this->save('agreement', 'saveAgreement'); }
    public function getStatistics() { return $this->data($this->configuration->getStatistics(MemberTenantContext::member())); }
    public function saveStatistics() { return $this->save('statistics', 'saveStatistics'); }
    public function getUser() { return $this->data($this->configuration->getUser(MemberTenantContext::member())); }
    public function saveUser() { return $this->save('user', 'saveUser'); }
    public function getLogin() { return $this->data($this->configuration->getLogin(MemberTenantContext::member())); }
    public function saveLogin() { return $this->save('login', 'saveLogin'); }

    private function save(string $scene, string $method)
    {
        $params = $this->request->post();
        $this->validate($params, WebsiteValidate::class . '.' . $scene);
        $this->configuration->$method(MemberTenantContext::member(), $params);
        return $this->success('操作成功');
    }
}
