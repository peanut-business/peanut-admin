<?php
declare(strict_types=1);

namespace app\common\model\notice;

use app\common\model\BaseModel;
use think\model\concern\SoftDelete;

/**
 * 通知模板模型
 */
class NoticeTemplate extends BaseModel
{
    use SoftDelete;

    protected $name = 'notice_template';
    protected $deleteTime = 'delete_time';

    /** 渠道：短信 */
    public const CHANNEL_SMS = 1;
    /** 渠道：邮件 */
    public const CHANNEL_EMAIL = 2;
    /** 渠道：推送 */
    public const CHANNEL_PUSH = 3;

    /**
     * 渲染模板（替换变量占位符）
     * @param array<string,mixed> $vars 变量映射 ['nickname' => 'Alice', 'code' => '123456']
     */
    public function render(array $vars): array
    {
        $title = $this->title;
        $content = $this->content;

        foreach ($vars as $key => $val) {
            $placeholder = '{' . $key . '}';
            $title = str_replace($placeholder, (string) $val, $title);
            $content = str_replace($placeholder, (string) $val, $content);
        }

        return ['title' => $title, 'content' => $content];
    }
}
