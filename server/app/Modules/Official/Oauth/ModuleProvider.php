<?php
declare(strict_types=1);

namespace app\Modules\Official\Oauth;

use app\Modules\Official\Oauth\Application\OAuthQueryService;
use app\Modules\Official\Oauth\Application\OAuthCommandService;
use app\Modules\Official\Oauth\Contracts\OAuthCommands;
use app\Modules\Official\Oauth\Contracts\OAuthQueries;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;

final class ModuleProvider implements ModuleProviderContract
{
    public function moduleKey(): string
    {
        return 'official.oauth';
    }

    public function queries(): OAuthQueries
    {
        return new OAuthQueryService();
    }

    public function commands(): OAuthCommands
    {
        return new OAuthCommandService();
    }
}
