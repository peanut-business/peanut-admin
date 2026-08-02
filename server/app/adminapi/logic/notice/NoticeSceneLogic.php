<?php
declare(strict_types=1);

namespace app\adminapi\logic\notice;

use app\common\logic\BaseLogic;
use app\common\model\notice\NoticeScene;

class NoticeSceneLogic extends BaseLogic
{
    public static function lists(): array
    {
        $list = NoticeScene::field([
            'id', 'code', 'name', 'description', 'recipient', 'variables',
            'sms_template_id', 'sms_content', 'sms_status', 'update_time',
        ])->order('id', 'asc')->select()->toArray();

        return ['list' => $list, 'total' => count($list)];
    }

    public static function detail(int $id): array
    {
        return NoticeScene::findOrEmpty($id)->toArray();
    }

    public static function save(array $params): bool
    {
        try {
            $scene = NoticeScene::findOrEmpty((int) $params['id']);
            if ($scene->isEmpty()) {
                throw new \RuntimeException('通知场景不存在');
            }

            $scene->sms_template_id = trim((string) ($params['sms_template_id'] ?? ''));
            $scene->sms_content = trim((string) ($params['sms_content'] ?? ''));
            $scene->sms_status = (int) $params['sms_status'];
            $scene->save();
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }
}
