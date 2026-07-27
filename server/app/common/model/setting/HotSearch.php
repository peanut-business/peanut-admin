<?php
declare(strict_types=1);

namespace app\common\model\setting;

use think\Model;

/**
 * 热门搜索词（无软删，仅记录 create_time）
 */
class HotSearch extends Model
{
    protected $name               = 'hot_search';
    protected $autoWriteTimestamp = 'int';
    protected $createTime         = 'create_time';
    protected $updateTime         = false;
}
