<?php
declare(strict_types=1);

namespace app\api\controller;

use think\App;
use app\common\execution\CurrentExecutionContext;

use app\Modules\Official\Oauth\Contracts\OfficialAccountCallbacks;
use app\common\application\BusinessException;
use app\common\service\external\ExternalTenantResolver;
use app\common\service\external\ExternalTenantResolutionException;
use app\common\execution\ExecutionContextStore;
use app\common\http\RequestTrace;
use app\common\service\module\ModuleExecutionBoundary;
use PeanutAdmin\Kernel\Module\ModuleException;

class OfficialAccountController extends BaseApiController
{
    public function __construct(
        App $app,
        CurrentExecutionContext $executionContext,
        private readonly OfficialAccountCallbacks $officialAccount,
        private readonly ExecutionContextStore $executionContexts,
        private readonly ModuleExecutionBoundary $modules,
        private readonly ExternalTenantResolver $externalTenants,
    )
    {
        parent::__construct($app, $executionContext);
    }


    public function verify()
    {
        $params = $this->request->get();
        try {
            $resolution = $this->externalTenants->verifiedCallback(
                ExternalTenantResolver::WECHAT_OFFICIAL_CALLBACK,
                (string)$this->request->route('binding'),
                'wechat.official.verify',
                $this->operationId(),
                fn(array $config): bool => $this->officialAccount->verify($params, $config),
            );
            $this->executionContexts->run(
                new \app\common\execution\SystemExecutionContext($resolution->context),
                fn() => $this->modules->assertExternalCallback('official.oauth'),
            );
        } catch (ExternalTenantResolutionException|ModuleException) {
            return response('callback rejected', 403, ['Content-Type' => 'text/plain; charset=utf-8']);
        }
        return response((string)($params['echostr'] ?? ''), 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public function callback()
    {
        $params = $this->request->get();
        try {
            $resolution = $this->externalTenants->verifiedCallback(
                ExternalTenantResolver::WECHAT_OFFICIAL_CALLBACK,
                (string)$this->request->route('binding'),
                'wechat.official.callback',
                $this->operationId(),
                function (array $config) use ($params): bool {
                    return strtolower((string)($params['encrypt_type'] ?? '')) !== 'aes'
                        && $this->officialAccount->verify($params, $config);
                },
            );
            $result = $this->executionContexts->run(
                new \app\common\execution\SystemExecutionContext($resolution->context),
                function () use ($resolution): string {
                    $this->modules->assertExternalCallback('official.oauth');
                    return $this->officialAccount->handlePlain(
                        $resolution->context,
                        (string)$this->request->getContent(),
                    );
                },
            );
        } catch (ExternalTenantResolutionException|ModuleException|BusinessException) {
            return response('callback rejected', 403, ['Content-Type' => 'text/plain; charset=utf-8']);
        }
        $contentType = $result === 'success' ? 'text/plain; charset=utf-8' : 'application/xml; charset=utf-8';
        return response($result, 200, ['Content-Type' => $contentType]);
    }

    private function operationId(): string
    {
        return RequestTrace::id($this->request, 'wechat');
    }
}
