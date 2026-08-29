<?php
declare(strict_types=1);

namespace app\common\validate;

use app\common\support\ExportPageInfo;
use PeanutAdmin\Kernel\Authorization\Application\PageRequest;

/** Shared validation rule for normal pages and explicit unpaged/export reads. */
trait PageSizeRule
{
    protected function pageSizeMax($value, $rule, array $data): bool|string
    {
        $maximum = (int)($data['page_type'] ?? 1) === 0
            ? ExportPageInfo::MAX_ROWS
            : PageRequest::MAX_PAGE_SIZE;

        return (int)$value <= $maximum
            ? true
            : sprintf('每页数量须在1-%d之间', $maximum);
    }
}
