<?php
declare(strict_types=1);

namespace app\common\model\setting;

use think\Model;

/**
 * Tenant-owned 热门搜索词（无软删，仅记录 create_time）。
 * 开关由 pa_tenant_setting 的 hot-search namespace 持有。
 */
class HotSearch extends Model
{
    protected $name               = 'hot_search';
    protected $autoWriteTimestamp = 'int';
    protected $createTime         = 'create_time';
    protected $updateTime         = false;
}
