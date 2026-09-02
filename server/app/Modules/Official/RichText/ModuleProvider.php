<?php
declare(strict_types=1);

namespace app\Modules\Official\RichText;

use app\common\composition\ModuleBindingContributor;
use app\common\execution\CurrentExecutionContext;
use app\Modules\Official\RichText\Application\RichTextDocumentService;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;
use think\App;

final class ModuleProvider implements ModuleProviderContract, ModuleBindingContributor
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
                trim((string)env('RICH_TEXT_COLLABORATION_URL', '')),
                trim((string)env('RICH_TEXT_COLLABORATION_SECRET', '')),
            ),
        ];
    }
}
