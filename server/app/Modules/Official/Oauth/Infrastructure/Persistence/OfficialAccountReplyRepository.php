<?php
declare(strict_types=1);

namespace app\Modules\Official\Oauth\Infrastructure\Persistence;

use app\common\application\BusinessException;
use app\common\enum\channel\OfficialAccountEnum;
use app\common\http\PageResult;
use app\common\support\PaginationInput;
use app\Modules\Official\Oauth\Model\OfficialAccountReply;

final class OfficialAccountReplyRepository
{
    public function lists(array $params): PageResult
    {
        $query = OfficialAccountReply::where([])->field([
            'id', 'name', 'keyword', 'reply_type', 'matching_type',
            'content_type', 'content', 'status', 'sort', 'create_time', 'update_time',
        ]);
        if (!empty($params['reply_type'])) {
            $query->where('reply_type', (int)$params['reply_type']);
        }
        $result = PaginationInput::from($params)->result($query->order(['sort' => 'desc', 'id' => 'desc']));
        return new PageResult(
            array_map(static fn($item): array => $item->toArray(), $result->items),
            $result->total,
            $result->page,
            $result->pageSize,
        );
    }

    public function detail(int $id): array
    {
        $reply = OfficialAccountReply::where([])->findOrEmpty($id);
        return $reply->isEmpty() ? [] : $reply->toArray();
    }

    public function create(array $data): void
    {
        $this->disableOtherSingletons((int)$data['reply_type'], (int)$data['status']);
        OfficialAccountReply::create($data);
    }

    public function update(int $id, array $data): void
    {
        $reply = OfficialAccountReply::where('id', $id)->lock(true)->findOrEmpty();
        if ($reply->isEmpty()) {
            throw BusinessException::notFound('OAUTH_REPLY_NOT_FOUND', '自动回复不存在');
        }
        $this->disableOtherSingletons((int)$data['reply_type'], (int)$data['status'], $id);
        $reply->save($data);
    }

    public function delete(int $id): void
    {
        $reply = OfficialAccountReply::where('id', $id)->lock(true)->findOrEmpty();
        if ($reply->isEmpty()) {
            throw BusinessException::notFound('OAUTH_REPLY_NOT_FOUND', '自动回复不存在');
        }
        $reply->delete();
    }

    public function updateStatus(int $id, int $status): void
    {
        $reply = OfficialAccountReply::where('id', $id)->lock(true)->findOrEmpty();
        if ($reply->isEmpty()) {
            throw BusinessException::notFound('OAUTH_REPLY_NOT_FOUND', '自动回复不存在');
        }
        $this->disableOtherSingletons((int)$reply->reply_type, $status, $id);
        $reply->status = $status;
        $reply->save();
    }

    /** @return list<array<string,mixed>> */
    public function activeKeywords(): array
    {
        return OfficialAccountReply::where([
            'reply_type' => OfficialAccountEnum::REPLY_KEYWORD,
            'status' => 1,
        ])->order(['sort' => 'asc', 'id' => 'asc'])->select()->toArray();
    }

    public function activeSingleton(int $type): ?array
    {
        $reply = OfficialAccountReply::where(['reply_type' => $type, 'status' => 1])
            ->order('id', 'desc')->findOrEmpty();
        return $reply->isEmpty() ? null : $reply->toArray();
    }

    private function disableOtherSingletons(int $type, int $status, int $exceptId = 0): void
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
        $ids = array_map('intval', $query->lock(true)->column('id'));
        if ($ids !== []) {
            OfficialAccountReply::whereIn('id', $ids)->update(['status' => 0]);
        }
    }
}
