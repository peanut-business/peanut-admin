<?php
declare(strict_types=1);

namespace app\adminapi\logic\setting;

use app\common\enum\channel\OfficialAccountEnum;
use app\common\logic\BaseLogic;
use app\common\model\channel\OfficialAccountReply;
use think\facade\Db;

class OfficialAccountReplyLogic extends BaseLogic
{
    public static function lists(array $params): array
    {
        $pageNo = max(1, (int)($params['page_no'] ?? 1));
        $pageSize = min(100, max(1, (int)($params['page_size'] ?? 15)));
        $query = OfficialAccountReply::field([
            'id', 'name', 'keyword', 'reply_type', 'matching_type',
            'content_type', 'content', 'status', 'sort', 'create_time', 'update_time',
        ]);
        if (!empty($params['reply_type'])) {
            $query->where('reply_type', (int)$params['reply_type']);
        }
        $total = (clone $query)->count();
        $list = $query->order(['sort' => 'desc', 'id' => 'desc'])
            ->page($pageNo, $pageSize)->select()->toArray();
        return ['list' => $list, 'total' => $total, 'page_no' => $pageNo, 'page_size' => $pageSize];
    }

    public static function detail(int $id): array
    {
        $reply = OfficialAccountReply::findOrEmpty($id);
        return $reply->isEmpty() ? [] : $reply->toArray();
    }

    public static function add(array $params): bool
    {
        try {
            Db::transaction(function () use ($params): void {
                $data = self::normalize($params);
                self::disableOtherSingletons($data['reply_type'], $data['status']);
                OfficialAccountReply::create($data);
            });
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function edit(array $params): bool
    {
        try {
            Db::transaction(function () use ($params): void {
                $reply = OfficialAccountReply::where('id', (int)$params['id'])->lock(true)->findOrEmpty();
                if ($reply->isEmpty()) {
                    throw new \RuntimeException('自动回复不存在');
                }
                $data = self::normalize($params);
                self::disableOtherSingletons($data['reply_type'], $data['status'], (int)$reply->id);
                $reply->save($data);
            });
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function delete(int $id): bool
    {
        try {
            Db::transaction(function () use ($id): void {
                $reply = OfficialAccountReply::where('id', $id)->lock(true)->findOrEmpty();
                if ($reply->isEmpty()) {
                    throw new \RuntimeException('自动回复不存在');
                }
                $reply->delete();
            });
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function updateStatus(int $id, int $status): bool
    {
        try {
            Db::transaction(function () use ($id, $status): void {
                $reply = OfficialAccountReply::where('id', $id)->lock(true)->findOrEmpty();
                if ($reply->isEmpty()) {
                    throw new \RuntimeException('自动回复不存在');
                }
                self::disableOtherSingletons((int)$reply->reply_type, $status, $id);
                $reply->status = $status;
                $reply->save();
            });
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function resolve(array $message): ?array
    {
        $messageType = strtolower((string)($message['MsgType'] ?? ''));
        if ($messageType === 'event' && strtolower((string)($message['Event'] ?? '')) === 'subscribe') {
            return self::activeSingleton(OfficialAccountEnum::REPLY_SUBSCRIBE);
        }
        if ($messageType !== 'text') {
            return null;
        }

        $content = (string)($message['Content'] ?? '');
        $keywords = OfficialAccountReply::where([
            'reply_type' => OfficialAccountEnum::REPLY_KEYWORD,
            'status' => 1,
        ])->order(['sort' => 'asc', 'id' => 'asc'])->select();
        foreach ($keywords as $reply) {
            $keyword = (string)$reply->keyword;
            $matched = (int)$reply->matching_type === OfficialAccountEnum::MATCH_EXACT
                ? $content === $keyword
                : ($keyword !== '' && stripos($content, $keyword) !== false);
            if ($matched) {
                return $reply->toArray();
            }
        }
        return self::activeSingleton(OfficialAccountEnum::REPLY_DEFAULT);
    }

    private static function activeSingleton(int $type): ?array
    {
        $reply = OfficialAccountReply::where(['reply_type' => $type, 'status' => 1])
            ->order('id', 'desc')->findOrEmpty();
        return $reply->isEmpty() ? null : $reply->toArray();
    }

    private static function disableOtherSingletons(int $type, int $status, int $exceptId = 0): void
    {
        if ($status !== 1 || !in_array($type, [
            OfficialAccountEnum::REPLY_SUBSCRIBE,
            OfficialAccountEnum::REPLY_DEFAULT,
        ], true)) {
            return;
        }
        $query = OfficialAccountReply::where('reply_type', $type)->where('status', 1);
        if ($exceptId > 0) {
            $query->where('id', '<>', $exceptId);
        }
        $rows = $query->lock(true)->select();
        foreach ($rows as $row) {
            $row->status = 0;
            $row->save();
        }
    }

    private static function normalize(array $params): array
    {
        $replyType = (int)$params['reply_type'];
        return [
            'name' => trim((string)$params['name']),
            'keyword' => $replyType === OfficialAccountEnum::REPLY_KEYWORD
                ? trim((string)($params['keyword'] ?? '')) : '',
            'reply_type' => $replyType,
            'matching_type' => $replyType === OfficialAccountEnum::REPLY_KEYWORD
                ? (int)($params['matching_type'] ?? OfficialAccountEnum::MATCH_EXACT)
                : OfficialAccountEnum::MATCH_EXACT,
            'content_type' => OfficialAccountEnum::CONTENT_TEXT,
            'content' => trim((string)$params['content']),
            'status' => (int)$params['status'],
            'sort' => $replyType === OfficialAccountEnum::REPLY_KEYWORD ? (int)($params['sort'] ?? 0) : 0,
        ];
    }
}
