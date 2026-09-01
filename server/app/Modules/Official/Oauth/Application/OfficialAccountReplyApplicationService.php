<?php
declare(strict_types=1);

namespace app\Modules\Official\Oauth\Application;

use app\common\http\PageResult;
use app\common\enum\channel\OfficialAccountEnum;
use app\common\application\BusinessException;
use app\Modules\Official\Oauth\Model\OfficialAccountReply;
use PeanutAdmin\Kernel\Persistence\TransactionManager;
use app\common\service\external\ExternalTenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use app\common\support\PaginationInput;

class OfficialAccountReplyApplicationService
{
    public function __construct(private readonly TransactionManager $transactions)
    {
    }

    public function lists(TenantContext $context, array $params): PageResult
    {
        $pagination = PaginationInput::from($params);
        $query = OfficialAccountReply::where([])->field([
            'id', 'name', 'keyword', 'reply_type', 'matching_type',
            'content_type', 'content', 'status', 'sort', 'create_time', 'update_time',
        ]);
        if (!empty($params['reply_type'])) {
            $query->where('reply_type', (int)$params['reply_type']);
        }
        $pageResult = $pagination->result($query->order(['sort' => 'desc', 'id' => 'desc']));
        $list = array_map(
            static fn($item): array => $item instanceof \think\Model ? $item->toArray() : (array) $item,
            $pageResult->items,
        );
        return new PageResult($list, $pageResult->total, $pageResult->page, $pageResult->pageSize);
    }

    public function detail(TenantContext $context, int $id): array
    {
        $reply = OfficialAccountReply::where([])->findOrEmpty($id);
        return $reply->isEmpty() ? [] : $reply->toArray();
    }

    public function add(TenantContext $context, array $params): bool
    {
        $this->transactions->run(function () use ($context, $params): void {
                $data = self::normalize($params);
                self::disableOtherSingletons($data['reply_type'], $data['status']);
                OfficialAccountReply::create($data);
        });
        return true;
    }

    public function edit(TenantContext $context, array $params): bool
    {
        $this->transactions->run(function () use ($context, $params): void {
                $reply = OfficialAccountReply::where('id', (int)$params['id'])->lock(true)->findOrEmpty();
                if ($reply->isEmpty()) {
                    throw BusinessException::notFound('OAUTH_REPLY_NOT_FOUND', '自动回复不存在');
                }
                $data = self::normalize($params);
                self::disableOtherSingletons($data['reply_type'], $data['status'], (int)$reply->id);
                $reply->save($data);
        });
        return true;
    }

    public function delete(TenantContext $context, int $id): bool
    {
        $this->transactions->run(function () use ($context, $id): void {
                $reply = OfficialAccountReply::where('id', $id)->lock(true)->findOrEmpty();
                if ($reply->isEmpty()) {
                    throw BusinessException::notFound('OAUTH_REPLY_NOT_FOUND', '自动回复不存在');
                }
                $reply->delete();
        });
        return true;
    }

    public function updateStatus(TenantContext $context, int $id, int $status): bool
    {
        $this->transactions->run(function () use ($context, $id, $status): void {
                $reply = OfficialAccountReply::where('id', $id)->lock(true)->findOrEmpty();
                if ($reply->isEmpty()) {
                    throw BusinessException::notFound('OAUTH_REPLY_NOT_FOUND', '自动回复不存在');
                }
                self::disableOtherSingletons((int)$reply->reply_type, $status, $id);
                $reply->status = $status;
                $reply->save();
        });
        return true;
    }

    public function resolve(TenantSystemContext $context, array $message): ?array
    {
        ExternalTenantContext::tenantId($context);
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
        $ids = array_map('intval', $query->lock(true)->column('id'));
        if ($ids !== []) {
            OfficialAccountReply::whereIn('id', $ids)->update(['status' => 0]);
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
