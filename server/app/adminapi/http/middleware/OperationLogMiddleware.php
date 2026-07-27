<?php
declare(strict_types=1);

namespace app\adminapi\http\middleware;

use app\common\model\log\OperationLog;

/**
 * 操作日志中间件（原生 TP 风格）
 *
 * 必须在 LoginMiddleware 之后执行（依赖 $request->adminInfo）。
 * 只记录写操作（POST），读操作（GET）不入库。
 * 记录发生在 $next() 之后：控制器抛异常时会被全局异常处理接管，
 * 从而跳过此处，故只有正常返回的写请求才会留痕。
 */
class OperationLogMiddleware
{
    /** 不记录的动作后缀（避免日志模块自我刷屏） */
    protected array $except = ['log/clear'];

    /** 敏感字段，入库前打码 */
    protected array $sensitive = ['password', 'salt', 'token'];

    public function handle($request, \Closure $next)
    {
        $response = $next($request);

        if (strtoupper($request->method()) === 'POST') {
            $this->record($request);
        }

        return $response;
    }

    protected function record($request): void
    {
        $uri = strtolower(trim($request->pathinfo(), '/'));
        foreach ($this->except as $skip) {
            if (str_ends_with($uri, $skip)) {
                return;
            }
        }

        $params = $request->post();
        foreach ($this->sensitive as $key) {
            if (isset($params[$key])) {
                $params[$key] = '******';
            }
        }

        $adminInfo = $request->adminInfo ?? [];

        try {
            OperationLog::create([
                'admin_id' => (int)($adminInfo['id'] ?? 0),
                'username' => (string)($adminInfo['username'] ?? ''),
                'ip'       => (string)$request->ip(),
                'uri'      => $uri,
                'method'   => strtoupper((string)$request->method()),
                'params'   => json_encode($params, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable) {
            // 记录日志失败不得影响主流程
        }
    }
}
