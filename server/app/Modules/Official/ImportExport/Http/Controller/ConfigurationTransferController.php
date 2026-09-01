<?php
declare(strict_types=1);

namespace app\Modules\Official\ImportExport\Http\Controller;

use app\Modules\Official\ImportExport\Application\TenantConfigurationTransferService;
use app\adminapi\controller\BaseAdminController;
use app\common\dto\authorization\AdminPrincipal;
use app\common\execution\CurrentExecutionContext;
use app\common\service\audit\OperationLogTenantContext;
use think\App;
use app\common\application\BusinessException;

/** Tenant-scoped, path-free configuration package HTTP Host. */
final class ConfigurationTransferController extends BaseAdminController
{
    public function __construct(
        App $app,
        CurrentExecutionContext $executionContext,
        private readonly TenantConfigurationTransferService $transfers,
    ) {
        parent::__construct($app, $executionContext);
    }

    public function export()
    {
        return $this->data($this->transfers->export(
                OperationLogTenantContext::member(),
                AdminPrincipal::fromArray($this->adminInfo),
        ));
    }

    public function dryRun()
    {
        [$package, $secretBindings, $conflictPolicy] = $this->requestPayload();
            return $this->data($this->transfers->dryRun(
                OperationLogTenantContext::member(),
                AdminPrincipal::fromArray($this->adminInfo),
                $package,
                $secretBindings,
                $conflictPolicy,
        ));
    }

    public function apply()
    {
        [$package, $secretBindings, $conflictPolicy] = $this->requestPayload();
            return $this->data($this->transfers->apply(
                OperationLogTenantContext::member(),
                AdminPrincipal::fromArray($this->adminInfo),
                $package,
                $secretBindings,
                $conflictPolicy,
        ));
    }

    /** @return array{0:array<string,mixed>|string,1:array<string,mixed>,2:string} */
    private function requestPayload(): array
    {
        $payload = $this->request->post();
        $keys = array_keys($payload);
        sort($keys, SORT_STRING);
        if ($keys !== ['conflict_policy', 'package', 'secret_bindings']
            || (!is_array($payload['package']) && !is_string($payload['package']))
            || !is_array($payload['secret_bindings'])
            || array_is_list($payload['secret_bindings'])
            || !is_string($payload['conflict_policy'])
        ) {
            throw BusinessException::invalid('TRANSFER_REQUEST_INVALID', '配置转移请求无效');
        }
        return [
            $payload['package'],
            $payload['secret_bindings'],
            $payload['conflict_policy'],
        ];
    }

}
