<?php
declare(strict_types=1);

if (!function_exists('linear_to_tree')) {
    function linear_to_tree(array $data, string $childrenKey = 'children', string $idKey = 'id', string $pidKey = 'pid'): array
    {
        $map = [];
        foreach ($data as $item) {
            $item = is_array($item) ? $item : (method_exists($item, 'toArray') ? $item->toArray() : (array)$item);
            $map[$item[$idKey]] = $item;
            $map[$item[$idKey]][$childrenKey] = [];
        }
        $tree = [];
        foreach ($map as &$item) {
            $pid = $item[$pidKey] ?? 0;
            if ($pid == 0 || !isset($map[$pid])) {
                $tree[] = &$item;
            } else {
                $map[$pid][$childrenKey][] = &$item;
            }
        }
        unset($item);
        return $tree;
    }
}

