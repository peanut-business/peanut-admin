<?php
declare(strict_types=1);

namespace app\adminapi\controller\config;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\config\ConfigLogic;
use app\adminapi\validate\config\WebsiteValidate;
use InvalidArgumentException;

class ConfigController extends BaseAdminController
{
    public function getWebsite()
    {
        return $this->data(ConfigLogic::getWebsite());
    }

    public function saveWebsite()
    {
        try {
            ConfigLogic::saveWebsite($this->request->post());
        } catch (InvalidArgumentException $exception) {
            return $this->fail($exception->getMessage());
        }
        return $this->success('操作成功');
    }

    public function getCopyright() { return $this->data(ConfigLogic::getCopyright()); }
    public function saveCopyright() { return $this->save('copyright', 'saveCopyright'); }
    public function getAgreement() { return $this->data(ConfigLogic::getAgreement()); }
    public function saveAgreement() { return $this->save('agreement', 'saveAgreement'); }
    public function getStatistics() { return $this->data(ConfigLogic::getStatistics()); }
    public function saveStatistics() { return $this->save('statistics', 'saveStatistics'); }
    public function getUser() { return $this->data(ConfigLogic::getUser()); }
    public function saveUser() { return $this->save('user', 'saveUser'); }
    public function getLogin() { return $this->data(ConfigLogic::getLogin()); }
    public function saveLogin() { return $this->save('login', 'saveLogin'); }

    private function save(string $scene, string $method)
    {
        $params = $this->request->post();
        $this->validate($params, WebsiteValidate::class . '.' . $scene);
        ConfigLogic::$method($params);
        return $this->success('操作成功');
    }
}
