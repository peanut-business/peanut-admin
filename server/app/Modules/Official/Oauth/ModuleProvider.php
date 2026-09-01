<?php
declare(strict_types=1);

namespace app\Modules\Official\Oauth;

use app\common\composition\ModuleBindingContributor;
use app\Modules\Official\Oauth\Application\OAuthQueryService;
use app\Modules\Official\Oauth\Application\OAuthCommandService;
use app\Modules\Official\Oauth\Contracts\OAuthCommands;
use app\Modules\Official\Oauth\Contracts\OAuthQueries;
use app\api\application\OAuthApplicationService;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;
use think\App;

final class ModuleProvider implements ModuleProviderContract, ModuleBindingContributor
{
    public function moduleKey(): string
    {
        return 'official.oauth';
    }

    public function queries(): OAuthQueries
    {
        return new OAuthQueryService();
    }

    public function bindings(): array
    {
        return [
            OAuthCommands::class => fn(App $app): OAuthCommands => new OAuthCommandService(
                $app->make(OAuthApplicationService::class),
            ),
            OAuthQueries::class => fn(): OAuthQueries => $this->queries(),
        ];
    }
}
