<?php

declare(strict_types=1);

namespace app\common\support;

use PeanutAdmin\Kernel\Authorization\Application\PageRequest;

/**
 * Normalized list pagination input.
 *
 * The application still accepts both the current page_no/page_size names and
 * the legacy page/limit names.  PageRequest remains the canonical contract for
 * a normal (bounded) page, so its validation is performed here instead of at
 * each individual Logic call site.
 */
final readonly class PaginationInput
{
    public int $page;
    public int $pageSize;

    public function __construct(public PageRequest $pageRequest)
    {
        $this->page = $pageRequest->page;
        $this->pageSize = $pageRequest->pageSize;
    }

    /**
     * @param array<string,mixed> $params
     */
    public static function from(
        array $params,
        int $defaultPage = 1,
        int $defaultPageSize = 15,
    ): self {
        $page = (int)($params['page_no'] ?? $params['page'] ?? $defaultPage);
        $pageSize = (int)($params['page_size'] ?? $params['limit'] ?? $defaultPageSize);

        // PageRequest deliberately owns the normal page-size upper bound (100).
        return new self(new PageRequest(max(1, $page), min(100, max(1, $pageSize))));
    }

    public function offset(): int
    {
        return $this->pageRequest->offset();
    }
}
