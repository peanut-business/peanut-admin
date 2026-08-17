<?php
declare(strict_types=1);

namespace app\Modules\Official\Article\Http;

use app\Modules\Official\Article\ModuleProvider;
use app\common\service\JsonService;
use PeanutAdmin\Kernel\Module\ModuleException;
use PDO;
use think\facade\Db;

final class ArticleModuleMiddleware
{
    public function handle($request, \Closure $next)
    {
        try {
            $pdo = Db::connect()->connect();
            if (!$pdo instanceof PDO) {
                throw new \RuntimeException('ARTICLE_MODULE_DATABASE_UNAVAILABLE');
            }
            (new ModuleProvider())->access($pdo)->assertTenant(
                (int)($request->tenantContext->tenantId ?? 0)
            );
        } catch (ModuleException $exception) {
            $status = in_array($exception->errorCode, ['MODULE_NOT_INSTALLED', 'MODULE_INSTALLATION_FAILED'], true)
                ? 50300
                : 40300;
            return JsonService::fail('文章模块当前不可用', ['error_code' => $exception->errorCode], $status);
        } catch (\Throwable) {
            return JsonService::fail('文章模块当前不可用', null, 50300);
        }

        return $next($request);
    }
}
