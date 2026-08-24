<?php
declare(strict_types=1);

namespace app\adminapi\controller\notice;

use app\Modules\Official\Notification\ModuleProvider;
use app\adminapi\controller\BaseAdminController;
use app\common\service\member\MemberTenantContext;

/**
 * 通知渠道配置控制器
 */
class NoticeChannelController extends BaseAdminController
{
    /**
     * 获取渠道配置（脱敏：密钥只返回是否已设置）
     */
    public function detail(): \think\Response
    {
        return $this->data((new ModuleProvider())->queries()->channelDetail(MemberTenantContext::member($this->request)));
    }

    /**
     * 保存渠道配置
     * 前端分块提交：{ section: 'sms_aliyun'|'sms_tencent'|'sms_default', ...fields }
     */
    public function save(): \think\Response
    {
        $post    = $this->request->post();
        $section = (string) ($post['section'] ?? '');

        unset($post['section']);
        try {
            (new ModuleProvider())->commands()->saveChannel(MemberTenantContext::member($this->request), $section, $post);
            return $this->success('保存成功');
        } catch (\Throwable $exception) {
            return $this->fail($exception->getMessage());
        }
    }
}
