<?php
declare(strict_types=1);

namespace app\common\model\setting;

use think\Model;

/**
 * Tenant-owned 热门搜索词（无软删，仅记录 create_time）。
 * hot_search.status 仍由 pa_config 提供实例级开关。
 */
class HotSearch extends Model
{
    protected $name               = 'hot_search';
    protected $autoWriteTimestamp = 'int';
    protected $createTime         = 'create_time';
    protected $updateTime         = false;
}
