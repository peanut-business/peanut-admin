<?php
declare(strict_types=1);

namespace app\adminapi\logic\notice;

use app\common\logic\BaseLogic;
use app\common\service\notice\NoticeTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;

class NoticeSceneLogic extends BaseLogic
{
    public static function lists(TenantContext $context): array
    {
        self::clearError();
        $list = NoticeTenantRepository::scenes($context)->field([
            'id', 'code', 'name', 'description', 'recipient', 'variables',
            'sms_template_id', 'sms_content', 'sms_status', 'update_time',
        ])->order('id', 'asc')->select()->toArray();

        return ['list' => $list, 'total' => count($list)];
    }

    public static function detail(TenantContext $context, int $id): array
    {
        self::clearError();
        return NoticeTenantRepository::scenes($context)->where('id', $id)->findOrEmpty()->toArray();
    }

    public static function save(TenantContext $context, array $params): bool
    {
        self::clearError();
        try {
            $scene = NoticeTenantRepository::scenes($context)
                ->where('id', (int) $params['id'])
                ->findOrEmpty();
            if ($scene->isEmpty()) {
                throw new \RuntimeException('通知场景不存在');
            }

            $scene->sms_template_id = trim((string) ($params['sms_template_id'] ?? ''));
            $scene->sms_content = trim((string) ($params['sms_content'] ?? ''));
            $scene->sms_status = (int) $params['sms_status'];
            $scene->save();
            return true;
        } catch (\Throwable $e) {
            return self::fail($e);
        }
    }
}
