<?php
declare(strict_types=1);

namespace app\adminapi\controller\notice;

use app\adminapi\controller\BaseAdminController;
use app\common\service\ConfigService;
use app\common\service\notice\NoticeService;

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
        $smsDefault = (string) ConfigService::get('notice', 'sms_default', 'aliyun');

        $smsAliyunRaw  = ConfigService::get('notice', 'sms_aliyun', '');
        $smsTencentRaw = ConfigService::get('notice', 'sms_tencent', '');
        $mailRaw       = ConfigService::get('notice', 'mail_smtp', '');

        $smsAliyun  = is_array($smsAliyunRaw)  ? $smsAliyunRaw  : (json_decode((string) $smsAliyunRaw, true)  ?? []);
        $smsTencent = is_array($smsTencentRaw) ? $smsTencentRaw : (json_decode((string) $smsTencentRaw, true) ?? []);
        $mail       = is_array($mailRaw)        ? $mailRaw       : (json_decode((string) $mailRaw, true)       ?? []);

        return $this->data([
            'sms_default' => $smsDefault,
            'sms_aliyun'  => [
                'access_key_id'     => $smsAliyun['access_key_id']     ?? '',
                'access_key_secret' => empty($smsAliyun['access_key_secret']) ? '' : '******',
                'sign_name'         => $smsAliyun['sign_name']         ?? '',
            ],
            'sms_tencent' => [
                'secret_id'  => $smsTencent['secret_id']  ?? '',
                'secret_key' => empty($smsTencent['secret_key']) ? '' : '******',
                'sdk_app_id' => $smsTencent['sdk_app_id'] ?? '',
                'sign_name'  => $smsTencent['sign_name']  ?? '',
                'region'     => $smsTencent['region']     ?? 'ap-guangzhou',
            ],
            'mail_smtp' => [
                'host'       => $mail['host']      ?? '',
                'port'       => (int) ($mail['port'] ?? 465),
                'username'   => $mail['username']  ?? '',
                'password'   => empty($mail['password']) ? '' : '******',
                'from_name'  => $mail['from_name'] ?? '',
                'encryption' => $mail['encryption'] ?? 'ssl',
            ],
            'status' => NoticeService::channelStatus(),
        ]);
    }

    /**
     * 保存渠道配置
     * 前端分块提交：{ section: 'sms_aliyun'|'sms_tencent'|'sms_default'|'mail_smtp', ...fields }
     */
    public function save(): \think\Response
    {
        $post    = $this->request->post();
        $section = (string) ($post['section'] ?? '');

        $allowed = ['sms_default', 'sms_aliyun', 'sms_tencent', 'mail_smtp'];
        if (!in_array($section, $allowed, true)) {
            return $this->fail('无效的配置节');
        }

        unset($post['section']);

        if ($section === 'sms_default') {
            ConfigService::set('notice', 'sms_default', $post['value'] ?? 'aliyun');
            return $this->success('保存成功');
        }

        // 密钥脱敏：'******' 表示前端未修改，跳过该字段
        $existing = ConfigService::get('notice', $section, '');
        $current  = is_array($existing)
            ? $existing
            : (json_decode((string) $existing, true) ?? []);

        $secretKeys = ['access_key_secret', 'secret_key', 'password'];
        foreach ($secretKeys as $sk) {
            if (isset($post[$sk]) && $post[$sk] === '******') {
                $post[$sk] = $current[$sk] ?? '';
            }
        }

        ConfigService::set('notice', $section, $post);

        return $this->success('保存成功');
    }
}
