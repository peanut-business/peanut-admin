<?php
declare(strict_types=1);

namespace app\modules\official\rich_text\model;

use app\common\model\TenantOwnedModel;
use think\model\concern\SoftDelete;

final class RichTextDocument extends TenantOwnedModel
{
    use SoftDelete;

    protected $name = 'rich_text_document';
    protected $deleteTime = 'delete_time';
}
