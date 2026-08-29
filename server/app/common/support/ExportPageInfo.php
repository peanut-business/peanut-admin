<?php

declare(strict_types=1);

namespace app\common\support;

use InvalidArgumentException;

/** Mechanical metadata shared by the five paged export endpoints. */
final readonly class ExportPageInfo
{
    public const MAX_ROWS = 25000;

    public function __construct(
        public int $count,
        public int $pageSize,
        public int $sumPage,
        public int $maxPage,
        public int $allMaxSize,
        public int $pageStart,
        public int $pageEnd,
        public string $fileName,
    ) {
    }

    public static function from(
        int $count,
        int $pageSize,
        int $allMaxSize,
        string $fileName,
        int $pageEndLimit = 200,
    ): self {
        if ($pageSize < 1) {
            throw new InvalidArgumentException('Export page size must be positive.');
        }
        if ($allMaxSize < 1) {
            throw new InvalidArgumentException('Export maximum size must be positive.');
        }
        if ($pageEndLimit < 1) {
            throw new InvalidArgumentException('Export page end limit must be positive.');
        }

        $sumPage = max(1, (int)ceil(max(0, $count) / $pageSize));

        return new self(
            count: $count,
            pageSize: $pageSize,
            sumPage: $sumPage,
            maxPage: (int)floor($allMaxSize / $pageSize),
            allMaxSize: $allMaxSize,
            pageStart: 1,
            pageEnd: min($sumPage, $pageEndLimit),
            fileName: $fileName,
        );
    }

    /** @return array{count:int,page_size:int,sum_page:int,max_page:int,all_max_size:int,page_start:int,page_end:int,file_name:string} */
    public function toArray(): array
    {
        return [
            'count' => $this->count,
            'page_size' => $this->pageSize,
            'sum_page' => $this->sumPage,
            'max_page' => $this->maxPage,
            'all_max_size' => $this->allMaxSize,
            'page_start' => $this->pageStart,
            'page_end' => $this->pageEnd,
            'file_name' => $this->fileName,
        ];
    }
}
