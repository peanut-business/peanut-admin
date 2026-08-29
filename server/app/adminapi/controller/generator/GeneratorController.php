<?php
declare(strict_types=1);

namespace app\adminapi\controller\generator;

use think\App;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\application\generator\GeneratorApplicationService;
use app\adminapi\service\generator\GeneratorArchiveService;
use app\adminapi\validate\generator\GeneratorValidate;
use app\common\service\instance\InstanceToolAccessGuard;
use app\common\service\JsonService;
use think\response\Json;

class GeneratorController extends BaseAdminController
{
    public function __construct(App $app, private readonly GeneratorApplicationService $generator)
    {
        parent::__construct($app);
    }

    public function sourceTables()
    {
        $denial = $this->instanceToolAccessDenial();
        if ($denial !== null) return $denial;

        $params = $this->request->get();
        $this->validate($params, GeneratorValidate::class . '.source');
        $result = $this->generator->sourceTables($params);
        return $this->data($result);
    }

    public function lists()
    {
        $denial = $this->instanceToolAccessDenial();
        if ($denial !== null) return $denial;

        $params = $this->request->get();
        $this->validate($params, GeneratorValidate::class . '.lists');
        $result = $this->generator->lists($this->adminId, $params);
        return $this->data($result);
    }

    public function detail()
    {
        $denial = $this->instanceToolAccessDenial();
        if ($denial !== null) return $denial;

        $params = $this->request->get();
        $this->validate($params, GeneratorValidate::class . '.id');
        $result = $this->generator->detail($this->adminId, (int) $params['id']);
        return $result === false ? $this->fail($this->generator->getError()) : $this->data($result);
    }

    public function import()
    {
        $denial = $this->instanceToolAccessDenial();
        if ($denial !== null) return $denial;

        $params = $this->request->post();
        $this->validate($params, GeneratorValidate::class . '.import');
        $result = $this->generator->importTables($this->adminId, $params['table_names']);
        return $result ? $this->success('导入成功') : $this->fail($this->generator->getError());
    }

    public function sync()
    {
        $denial = $this->instanceToolAccessDenial();
        if ($denial !== null) return $denial;

        $params = $this->request->post();
        $this->validate($params, GeneratorValidate::class . '.id');
        $result = $this->generator->sync($this->adminId, (int) $params['id']);
        return $result ? $this->success('同步成功') : $this->fail($this->generator->getError());
    }

    public function update()
    {
        $denial = $this->instanceToolAccessDenial();
        if ($denial !== null) return $denial;

        $params = $this->request->post();
        $this->validate($params, GeneratorValidate::class . '.update');
        $result = $this->generator->update($this->adminId, $params);
        return $result ? $this->success('保存成功') : $this->fail($this->generator->getError());
    }

    public function delete()
    {
        $denial = $this->instanceToolAccessDenial();
        if ($denial !== null) return $denial;

        $params = $this->request->post();
        $this->validate($params, GeneratorValidate::class . '.ids');
        $result = $this->generator->delete($this->adminId, $params['ids']);
        return $result ? $this->success('删除成功') : $this->fail($this->generator->getError());
    }

    public function preview()
    {
        $denial = $this->instanceToolAccessDenial();
        if ($denial !== null) return $denial;

        $params = $this->request->post();
        $this->validate($params, GeneratorValidate::class . '.id');
        $result = $this->generator->preview($this->adminId, (int) $params['id']);
        return $result === false ? $this->fail($this->generator->getError()) : $this->data($result);
    }

    public function generate()
    {
        $denial = $this->instanceToolAccessDenial();
        if ($denial !== null) return $denial;

        $params = $this->request->post();
        $this->validate($params, GeneratorValidate::class . '.ids');
        $result = $this->generator->generate($this->adminId, $params['ids']);
        return $result === false ? $this->fail($this->generator->getError()) : $this->data($result);
    }

    public function download()
    {
        $denial = $this->instanceToolAccessDenial();
        if ($denial !== null) return $denial;

        $params = $this->request->get();
        $this->validate($params, GeneratorValidate::class . '.download');
        $file = $this->generator->consumeDownload($this->adminId, (string) $params['token']);
        if ($file === false) {
            return $this->fail($this->generator->getError());
        }
        $adminId = $this->adminId;
        register_shutdown_function(static function () use ($file, $adminId): void {
            try {
                GeneratorArchiveService::cleanup($file['archive_path'], $adminId);
            } catch (\Throwable) {
            }
        });
        return download($file['path'], $file['file_name']);
    }

    public function models()
    {
        $denial = $this->instanceToolAccessDenial();
        if ($denial !== null) return $denial;

        return $this->data($this->generator->models($this->adminId));
    }

    private function instanceToolAccessDenial(): ?Json
    {
        $guard = InstanceToolAccessGuard::fromConfiguredValue(config('deployment.mode'));
        return $guard->allows()
            ? null
            : throw \app\common\http\ApiProblem::fromEnvelope('实例级开发工具仅在 standalone 部署中可用', null, 40300);
    }
}
