<?php
declare(strict_types=1);

namespace app\adminapi\logic\notice;

use app\common\logic\BaseLogic;
use app\common\model\notice\NoticeTemplate;

/**
 * 通知模板 Logic
 */
class NoticeTemplateLogic extends BaseLogic
{
    /**
     * 列表（分页）
     * @param array<string,mixed> $params
     */
    public static function lists(array $params): array
    {
        $query = NoticeTemplate::whereNull('delete_time');

        if (!empty($params['name'])) {
            $query->whereLike('name', '%' . $params['name'] . '%');
        }
        if (isset($params['channel']) && $params['channel'] !== '') {
            $query->where('channel', (int) $params['channel']);
        }
        if (isset($params['is_disable']) && $params['is_disable'] !== '') {
            $query->where('is_disable', (int) $params['is_disable']);
        }

        $total = $query->count();
        $page  = max(1, (int) ($params['page']  ?? 1));
        $limit = max(1, (int) ($params['limit'] ?? 15));

        $list = $query->order('id', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        return ['total' => $total, 'list' => $list];
    }

    /**
     * 新增模板
     * @param array<string,mixed> $params
     */
    public static function add(array $params): bool
    {
        if (self::codeExists($params['code'])) {
            self::setError('模板标识已存在');
            return false;
        }

        NoticeTemplate::create([
            'name'       => $params['name'],
            'code'       => $params['code'],
            'channel'    => (int) $params['channel'],
            'title'      => $params['title'] ?? '',
            'content'    => $params['content'] ?? '',
            'is_disable' => (int) ($params['is_disable'] ?? 0),
            'remark'     => $params['remark'] ?? '',
        ]);

        return true;
    }

    /**
     * 编辑模板
     * @param array<string,mixed> $params
     */
    public static function edit(array $params): bool
    {
        $tpl = NoticeTemplate::findOrEmpty($params['id']);
        if ($tpl->isEmpty()) {
            self::setError('模板不存在');
            return false;
        }

        if (self::codeExists($params['code'], (int) $params['id'])) {
            self::setError('模板标识已被占用');
            return false;
        }

        $tpl->save([
            'name'       => $params['name'],
            'code'       => $params['code'],
            'channel'    => (int) $params['channel'],
            'title'      => $params['title'] ?? '',
            'content'    => $params['content'] ?? '',
            'is_disable' => (int) ($params['is_disable'] ?? 0),
            'remark'     => $params['remark'] ?? '',
        ]);

        return true;
    }

    /**
     * 删除模板
     * @param int[] $ids
     */
    public static function delete(array $ids): bool
    {
        NoticeTemplate::destroy($ids);
        return true;
    }

    /** 检查 code 唯一性（软删除范围内） */
    private static function codeExists(string $code, int $excludeId = 0): bool
    {
        $q = NoticeTemplate::whereNull('delete_time')->where('code', $code);
        if ($excludeId > 0) {
            $q->where('id', '<>', $excludeId);
        }
        return $q->count() > 0;
    }
}
