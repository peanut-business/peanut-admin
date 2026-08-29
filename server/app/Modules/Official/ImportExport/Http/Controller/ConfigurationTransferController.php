<?php
declare(strict_types=1);

namespace app\Modules\Official\ImportExport\Http\Controller;

use app\Modules\Official\ImportExport\Application\TenantConfigurationTransferService;
use app\adminapi\controller\BaseAdminController;
use app\common\dto\authorization\AdminPrincipal;
use app\common\service\audit\OperationLogTenantContext;
use think\App;

/** Tenant-scoped, path-free configuration package HTTP Host. */
final class ConfigurationTransferController extends BaseAdminController
{
    public function __construct(
        App $app,
        private readonly TenantConfigurationTransferService $transfers,
    ) {
        parent::__construct($app);
    }

    public function export()
    {
        try {
            return $this->data($this->transfers->export(
                OperationLogTenantContext::member(),
                AdminPrincipal::fromArray($this->adminInfo),
            ));
        } catch (\Throwable $exception) {
            return $this->fail($this->safeError($exception));
        }
    }

    public function dryRun()
    {
        try {
            [$package, $secretBindings, $conflictPolicy] = $this->requestPayload();
            return $this->data($this->transfers->dryRun(
                OperationLogTenantContext::member(),
                AdminPrincipal::fromArray($this->adminInfo),
                $package,
                $secretBindings,
                $conflictPolicy,
            ));
        } catch (\Throwable $exception) {
            return $this->fail($this->safeError($exception));
        }
    }

    public function apply()
    {
        try {
            [$package, $secretBindings, $conflictPolicy] = $this->requestPayload();
            return $this->data($this->transfers->apply(
                OperationLogTenantContext::member(),
                AdminPrincipal::fromArray($this->adminInfo),
                $package,
                $secretBindings,
                $conflictPolicy,
            ));
        } catch (\Throwable $exception) {
            return $this->fail($this->safeError($exception));
        }
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
            throw new \RuntimeException('TRANSFER_REQUEST_INVALID');
        }
        return [
            $payload['package'],
            $payload['secret_bindings'],
            $payload['conflict_policy'],
        ];
    }

    private function safeError(\Throwable $exception): string
    {
        return match ($exception->getMessage()) {
            'TRANSFER_PERMISSION_DENIED' => '暂无访问权限',
            'TRANSFER_CHECKSUM_INVALID' => '配置包校验和不匹配',
            'TRANSFER_SCOPE_MISMATCH', 'TRANSFER_ADAPTER_SCOPE_INVALID' => '配置包作用域不匹配',
            'TRANSFER_CONFLICT' => '目标配置已经变化，请重新预演',
            'TRANSFER_SECRET_REBIND_REQUIRED' => '请先重新绑定配置包中的秘密引用',
            'TRANSFER_SECRET_REBIND_INVALID', 'TRANSFER_SECRET_REFERENCE_UNKNOWN' => '秘密重绑定无效',
            'TRANSFER_SECRET_PROTECTOR_UNAVAILABLE' => '当前环境无法安全保存秘密配置',
            default => '配置转移请求无效',
        };
    }
}
