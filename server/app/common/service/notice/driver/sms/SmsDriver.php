<?php
declare(strict_types=1);

namespace app\common\service\notice\driver\sms;

/** @deprecated Compatibility alias for the core template-SMS driver base. */
class_alias(
    \PeanutAdmin\NotificationSms\Sms\TemplateSmsDriver::class,
    __NAMESPACE__ . '\\SmsDriver',
);
