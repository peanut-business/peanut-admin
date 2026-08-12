<?php
declare(strict_types=1);

namespace app\common\service\notice;

interface NoticeSmsSender
{
    /** @return array{success:bool,provider:string,error:string,result:array<string,mixed>} */
    public function send(
        string $mobile,
        string $templateId,
        array $variables,
        ?callable $beforeSend = null
    ): array;
}
