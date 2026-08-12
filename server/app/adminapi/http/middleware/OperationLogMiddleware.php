<?php
declare(strict_types=1);

namespace app\adminapi\http\middleware;

use app\adminapi\service\OperationLogService;
use app\common\service\audit\OperationLogDiagnostics;
use app\common\service\audit\OperationLogTenantContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use think\facade\Log;

/**
 * 操作日志中间件（原生 TP 风格）
 *
 * 必须在 LoginMiddleware 之后执行（依赖 $request->adminInfo）。
 * 记录 POST/PUT/PATCH/DELETE；GET 等只读请求不入库。
 * finally 保证正常失败 envelope 与异常写请求都尝试留痕，日志失败不影响主流程。
 */
class OperationLogMiddleware
{
    /** 不记录的动作后缀（避免日志模块自我刷屏） */
    protected array $except = ['log/clear'];

    public function handle($request, \Closure $next)
    {
        try {
            $context = OperationLogTenantContext::member($request);
        } catch (\Throwable $exception) {
            Log::warning('operation_log_tenant_context_unavailable', OperationLogDiagnostics::attributes(null));
            throw $exception;
        }
        try {
            return $next($request);
        } finally {
            if (in_array(strtoupper((string)$request->method()), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                $this->record($context, $request);
            }
        }
    }

    protected function record(TenantContext $context, $request): void
    {
        $uri = strtolower(trim($request->pathinfo(), '/'));
        foreach ($this->except as $skip) {
            if (str_ends_with($uri, $skip)) {
                return;
            }
        }

        $adminInfo = $request->adminInfo ?? [];

        try {
            OperationLogService::record(
                $context,
                (int)($adminInfo['id'] ?? 0),
                (string)($adminInfo['username'] ?? ''),
                (string)$request->ip(),
                $uri,
                (string)$request->method(),
                $request->post()
            );
        } catch (\Throwable $exception) {
            Log::error('operation_log_write_failed', OperationLogDiagnostics::attributes($context) + [
                'exception' => $exception::class,
            ]);
            // 记录日志失败不得影响主流程
        }
    }
}
