<?php
declare(strict_types=1);

namespace app\Modules\Official\Oauth\Application;

use app\common\http\PageResult;
use app\common\enum\channel\OfficialAccountEnum;
use app\Modules\Official\Oauth\Infrastructure\Persistence\OfficialAccountReplyRepository;
use PeanutAdmin\Kernel\Persistence\TransactionManager;
use app\common\service\external\ExternalTenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;
use PeanutAdmin\Kernel\Auth\TenantContext;

final class OfficialAccountReplyApplicationService
{
    public function __construct(
        private readonly TransactionManager $transactions,
        private readonly OfficialAccountReplyRepository $replies,
    ) {}

    public function lists(TenantContext $context, array $params): PageResult
    {
        return $this->replies->lists($params);
    }

    public function detail(TenantContext $context, int $id): array
    {
        return $this->replies->detail($id);
    }

    public function add(TenantContext $context, array $params): bool
    {
        $this->transactions->run(function () use ($context, $params): void {
                $this->replies->create(self::normalize($params));
        });
        return true;
    }

    public function edit(TenantContext $context, array $params): bool
    {
        $this->transactions->run(function () use ($context, $params): void {
            $this->replies->update((int)$params['id'], self::normalize($params));
        });
        return true;
    }

    public function delete(TenantContext $context, int $id): bool
    {
        $this->transactions->run(function () use ($context, $id): void {
            $this->replies->delete($id);
        });
        return true;
    }

    public function updateStatus(TenantContext $context, int $id, int $status): bool
    {
        $this->transactions->run(function () use ($context, $id, $status): void {
            $this->replies->updateStatus($id, $status);
        });
        return true;
    }

    public function resolve(TenantSystemContext $context, array $message): ?array
    {
        ExternalTenantContext::tenantId($context);
        $messageType = strtolower((string)($message['MsgType'] ?? ''));
        if ($messageType === 'event' && strtolower((string)($message['Event'] ?? '')) === 'subscribe') {
            return $this->replies->activeSingleton(OfficialAccountEnum::REPLY_SUBSCRIBE);
        }
        if ($messageType !== 'text') {
            return null;
        }

        $content = (string)($message['Content'] ?? '');
        foreach ($this->replies->activeKeywords() as $reply) {
            $keyword = (string)$reply['keyword'];
            $matched = (int)$reply['matching_type'] === OfficialAccountEnum::MATCH_EXACT
                ? $content === $keyword
                : ($keyword !== '' && stripos($content, $keyword) !== false);
            if ($matched) {
                return $reply;
            }
        }
        return $this->replies->activeSingleton(OfficialAccountEnum::REPLY_DEFAULT);
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
