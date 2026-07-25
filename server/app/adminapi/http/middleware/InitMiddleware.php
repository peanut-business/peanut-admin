<?php
declare(strict_types=1);

namespace app\adminapi\http\middleware;

use app\adminapi\controller\BaseAdminController;
use think\exception\HttpException;

class InitMiddleware
{
    public function handle($request, \Closure $next)
    {
        $controller = str_replace('.', '\\', $request->controller());
        $class      = '\\app\\adminapi\\controller\\' . $controller . 'Controller';

        if (!class_exists($class)) {
            throw new HttpException(404, 'controller not exists: ' . $class);
        }

        $obj = invoke($class);
        if (!($obj instanceof BaseAdminController)) {
            throw new HttpException(404, 'Invalid controller: ' . $class);
        }

        $request->controllerObject = $obj;
        return $next($request);
    }
}
