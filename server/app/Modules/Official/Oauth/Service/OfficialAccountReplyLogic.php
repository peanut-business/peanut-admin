<?php
declare(strict_types=1);

namespace app\Modules\Official\Oauth\Service;

use app\common\enum\channel\OfficialAccountEnum;
use app\common\logic\BaseLogic;
use app\Modules\Official\Oauth\Model\OfficialAccountReply;
use think\facade\Db;
use app\common\service\external\ExternalTenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use app\common\service\member\MemberTenantContext;
use app\common\support\PaginationInput;

class OfficialAccountReplyLogic extends BaseLogic
{
    public static function lists(TenantContext $context, array $params): array
    {
        $pagination = PaginationInput::from($params);
        $tenantId = MemberTenantContext::tenantId($context);
        $query = OfficialAccountReply::where('tenant_id', $tenantId)->field([
            'id', 'name', 'keyword', 'reply_type', 'matching_type',
            'content_type', 'content', 'status', 'sort', 'create_time', 'update_time',
        ]);
        if (!empty($params['reply_type'])) {
            $query->where('reply_type', (int)$params['reply_type']);
        }
        $total = (clone $query)->count();
        $list = $query->order(['sort' => 'desc', 'id' => 'desc'])
            ->page($pagination->page, $pagination->pageSize)->select()->toArray();
        return [
            'list' => $list,
            'total' => $total,
            'page_no' => $pagination->page,
            'page_size' => $pagination->pageSize,
        ];
    }

    public static function detail(TenantContext $context, int $id): array
    {
        $reply = OfficialAccountReply::where('tenant_id', MemberTenantContext::tenantId($context))->findOrEmpty($id);
        return $reply->isEmpty() ? [] : $reply->toArray();
    }

    public static function add(TenantContext $context, array $params): bool
    {
        try {
            Db::transaction(function () use ($context, $params): void {
                $data = self::normalize($params);
                $data['tenant_id'] = MemberTenantContext::tenantId($context);
                self::disableOtherSingletons($data['tenant_id'], $data['reply_type'], $data['status']);
                OfficialAccountReply::create($data);
            });
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function edit(TenantContext $context, array $params): bool
    {
        try {
            Db::transaction(function () use ($context, $params): void {
                $tenantId = MemberTenantContext::tenantId($context);
                $reply = OfficialAccountReply::where(['tenant_id' => $tenantId, 'id' => (int)$params['id']])->lock(true)->findOrEmpty();
                if ($reply->isEmpty()) {
                    throw new \RuntimeException('自动回复不存在');
                }
                $data = self::normalize($params);
                self::disableOtherSingletons($tenantId, $data['reply_type'], $data['status'], (int)$reply->id);
                $reply->save($data);
            });
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function delete(TenantContext $context, int $id): bool
    {
        try {
            Db::transaction(function () use ($context, $id): void {
                $reply = OfficialAccountReply::where(['tenant_id' => MemberTenantContext::tenantId($context), 'id' => $id])->lock(true)->findOrEmpty();
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

    public static function updateStatus(TenantContext $context, int $id, int $status): bool
    {
        try {
            Db::transaction(function () use ($context, $id, $status): void {
                $tenantId = MemberTenantContext::tenantId($context);
                $reply = OfficialAccountReply::where(['tenant_id' => $tenantId, 'id' => $id])->lock(true)->findOrEmpty();
                if ($reply->isEmpty()) {
                    throw new \RuntimeException('自动回复不存在');
                }
                self::disableOtherSingletons($tenantId, (int)$reply->reply_type, $status, $id);
                $reply->status = $status;
                $reply->save();
            });
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function resolve(TenantSystemContext $context, array $message): ?array
    {
        $tenantId = ExternalTenantContext::tenantId($context);
        $messageType = strtolower((string)($message['MsgType'] ?? ''));
        if ($messageType === 'event' && strtolower((string)($message['Event'] ?? '')) === 'subscribe') {
            return self::activeSingleton($tenantId, OfficialAccountEnum::REPLY_SUBSCRIBE);
        }
        if ($messageType !== 'text') {
            return null;
        }

        $content = (string)($message['Content'] ?? '');
        $keywords = OfficialAccountReply::where([
            'tenant_id' => $tenantId,
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
        return self::activeSingleton($tenantId, OfficialAccountEnum::REPLY_DEFAULT);
    }

    private static function activeSingleton(int $tenantId, int $type): ?array
    {
        $reply = OfficialAccountReply::where(['tenant_id' => $tenantId, 'reply_type' => $type, 'status' => 1])
            ->order('id', 'desc')->findOrEmpty();
        return $reply->isEmpty() ? null : $reply->toArray();
    }

    private static function disableOtherSingletons(int $tenantId, int $type, int $status, int $exceptId = 0): void
    {
        if ($status !== 1 || !in_array($type, [
            OfficialAccountEnum::REPLY_SUBSCRIBE,
            OfficialAccountEnum::REPLY_DEFAULT,
        ], true)) {
            return;
        }
        $query = OfficialAccountReply::where('tenant_id', $tenantId)->where('reply_type', $type)->where('status', 1);
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
