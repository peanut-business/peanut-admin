<?php
declare(strict_types=1);

namespace app\Modules\Official\RichText;

use app\common\execution\CurrentExecutionContext;
use app\Modules\Official\RichText\Application\RichTextDocumentService;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;
use think\App;
use think\facade\Config;

final class ModuleProvider implements ModuleProviderContract
{
    public function moduleKey(): string
    {
        return 'official.rich-text';
    }

    public function bindings(): array
    {
        return [
            RichTextDocumentService::class => fn(App $app): RichTextDocumentService => new RichTextDocumentService(
                $app->make(CurrentExecutionContext::class),
                trim((string)Config::get('peanut.rich_text.collaboration_url', '')),
                trim((string)Config::get('peanut.rich_text.collaboration_secret', '')),
            ),
        ];
    }
}
