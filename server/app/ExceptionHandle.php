<?php
namespace app;

use app\common\http\ApiProblem;
use app\common\http\ApiProblemMapper;
use app\common\http\RequestTrace;
use app\common\application\BusinessException;
use app\common\service\JsonService;
use app\common\service\runtime\OperationalLog;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\Response;
use Throwable;

/**
 * 应用异常处理类
 */
class ExceptionHandle extends Handle
{
    /**
     * 不需要记录信息（日志）的异常类列表
     * @var array
     */
    protected $ignoreReport = [
        HttpException::class,
        HttpResponseException::class,
        ModelNotFoundException::class,
        DataNotFoundException::class,
        ValidateException::class,
        ApiProblem::class,
        BusinessException::class,
    ];

    /**
     * 记录异常信息（包括日志或者其它方式记录）
     *
     * @access public
     * @param  Throwable $exception
     * @return void
     */
    public function report(Throwable $exception): void
    {
        // 使用内置的方式记录异常日志
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @access public
     * @param \think\Request   $request
     * @param Throwable $e
     * @return Response
     */
    public function render($request, Throwable $e): Response
    {
        $problem = (new ApiProblemMapper())->map($e);
        if ($problem instanceof ApiProblem) {
            $requestId = RequestTrace::id($request);
            $this->reportProblem($request, $problem, $requestId);
            return JsonService::response(
                $problem->apiCode(),
                $problem->getMessage(),
                $problem->data(),
                $problem->httpStatus,
            )->header(['X-Request-Id' => $requestId] + $problem->headers);
        }

        // 其他错误交给系统处理
        return parent::render($request, $e);
    }

    private function reportProblem($request, ApiProblem $problem, string $requestId): void
    {
        OperationalLog::warning('api_problem', [
            'method' => $request->method(),
            'path' => '/' . ltrim($request->pathinfo(), '/'),
            'error_code' => $problem->errorCode,
            'api_code' => $problem->apiCode(),
            'request_id' => $requestId,
        ]);
    }
}
