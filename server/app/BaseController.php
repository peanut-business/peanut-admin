<?php
declare (strict_types = 1);

namespace app;

use app\common\validate\InputValidator;
use app\common\validate\ValidatedInput;
use think\App;
use think\exception\ValidateException;

/**
 * 控制器基础类
 */
abstract class BaseController
{
    /**
     * Request实例
     * @var \think\Request
     */
    protected $request;

    /**
     * 应用实例
     * @var \think\App
     */
    protected $app;

    /**
     * 是否批量验证
     * @var bool
     */
    protected $batchValidate = false;

    /**
     * 控制器中间件
     * @var array
     */
    protected $middleware = [];

    /**
     * 构造方法
     * @access public
     * @param  App  $app  应用对象
     */
    public function __construct(App $app)
    {
        $this->app     = $app;
        $this->request = $this->app->request;

        // 控制器初始化
        $this->initialize();
    }

    // 初始化
    protected function initialize()
    {}

    /**
     * 验证数据
     * @access protected
     * @param  array        $data     数据
     * @param  string|array $validate 验证器名或者验证规则数组
     * @param  array        $message  提示信息
     * @param  bool         $batch    是否批量验证
     * @return ValidatedInput
     * @throws ValidateException
     */
    protected function validate(
        array $data,
        string|array $validate,
        array $message = [],
        bool $batch = false,
    ): ValidatedInput
    {
        return $this->app->make(InputValidator::class)->validate(
            $data,
            $validate,
            $message,
            $batch || $this->batchValidate,
        );
    }

}
