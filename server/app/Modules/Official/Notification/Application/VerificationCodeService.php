<?php
declare(strict_types=1);

namespace app\Modules\Official\Notification\Application;

use app\Modules\Official\Notification\Contracts\DeliveryResult;
use app\Modules\Official\Notification\Contracts\VerificationResult;
use app\Modules\Official\Notification\Infrastructure\Persistence\NoticeTenantRepository;
use app\common\enum\notice\NoticeSceneEnum;
use PeanutAdmin\Kernel\Context\AuthenticatedMemberContext;
use app\common\service\notice\NoticeTenantContext;
use app\common\service\notice\driver\sms\SmsDriverResult;
use app\common\execution\CurrentExecutionContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;
use PeanutAdmin\Kernel\Persistence\TransactionManager;
use PeanutAdmin\NotificationSms\Application\VerificationCodeSecret;
use PeanutAdmin\NotificationSms\Sms\NoticeSmsSender;

/**
 * 手机验证码发送与核验服务。
 */
class VerificationCodeService
{
    private const SEND_INTERVAL = 60;
    private const VALID_PERIOD = 300;

    public function __construct(
        private readonly NoticeSmsSender $sender,
        private readonly TransactionManager $transactions,
        private readonly CurrentExecutionContext $executionContext,
        private readonly bool $developmentMode,
    ) {
    }

    public function send(
        TenantContext|TenantSystemContext $context,
        string $sceneCode,
        string $mobile
    ): DeliveryResult
    {
        $tenantId = NoticeTenantContext::verificationTenantId(
            $this->executionContext,
            $context,
            'notice.verification.send',
        );
        if (!$this->validMobile($mobile)) {
            return new DeliveryResult(false, '', '手机号格式不正确');
        }

        if (!NoticeSceneEnum::isValid($sceneCode)) {
            return new DeliveryResult(false, '', '验证码场景不存在');
        }

        $scene = NoticeTenantRepository::scenes($this->executionContext, $context, 'notice.verification.send')
            ->where('code', $sceneCode)->findOrEmpty();
        if ($scene->isEmpty() || (int) $scene->sms_status !== $scene::STATUS_ENABLED) {
            return new DeliveryResult(false, '', '验证码场景未启用');
        }

        $templateId = trim((string) $scene->sms_template_id);
        $templateContent = trim((string) $scene->sms_content);
        if ($templateId === '' || $templateContent === '') {
            return new DeliveryResult(false, '', '短信模板未配置');
        }

        $code = $this->developmentMode
            ? '1234'
            : (string) random_int(
                10 ** (NoticeSceneEnum::CODE_LENGTH - 1),
                (10 ** NoticeSceneEnum::CODE_LENGTH) - 1
            );
        $content = $this->render($templateContent, ['code' => '****']);
        $reservation = $this->reserve(
            $context,
            $tenantId,
            (int)$scene->id,
            (string)$scene->name,
            $sceneCode,
            $mobile,
            $content,
            $templateId,
            $code,
        );
        if (!$reservation['execute']) {
            return $this->reservedDelivery($reservation['log'], $reservation['reason']);
        }

        $reservationKey = (string)$reservation['log']->reservation_key;
        $attemptStarted = false;
        try {
            $result = $this->sender->send(
                $context,
                $mobile,
                $templateId,
                ['code' => $code],
                function (string $provider) use ($context, $reservationKey, &$attemptStarted): void {
                    $this->markProviderAttemptStarted($context, $reservationKey, $provider);
                    $attemptStarted = true;
                },
            );
        } catch (\Throwable) {
            $outcome = $attemptStarted ? SmsDriverResult::OUTCOME_UNKNOWN : SmsDriverResult::OUTCOME_FAILED;
            $error = $attemptStarted ? '短信服务商调用结果未知，请稍后重试' : '短信发送前置处理失败';
            $this->finalizeReservation($context, $reservationKey, $outcome, '', $error, $templateId, []);
            return new DeliveryResult(false, '', $error);
        }

        $outcome = $this->normalizeOutcome($result);
        $provider = trim((string)($result['provider'] ?? ''));
        $error = trim((string)($result['error'] ?? ''));
        $receipt = is_array($result['result'] ?? null) ? $result['result'] : [];
        if ($outcome === SmsDriverResult::OUTCOME_UNKNOWN && $error === '') {
            $error = '短信服务商调用结果未知，请稍后重试';
        }
        try {
            $this->finalizeReservation(
                $context,
                $reservationKey,
                $outcome,
                $provider,
                $error,
                $templateId,
                $receipt,
            );
        } catch (\Throwable) {
            return new DeliveryResult(false, $provider, '短信服务商调用结果未知，请稍后重试');
        }

        return new DeliveryResult(
            $outcome === SmsDriverResult::OUTCOME_SUCCEEDED,
            $provider,
            $error,
            $receipt,
        );
    }

    public function verify(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        string $sceneCode,
        string $mobile,
        string $code
    ): VerificationResult
    {
        NoticeTenantContext::verificationTenantId($this->executionContext, $context, 'notice.verification.verify');
        if (!$this->validMobile($mobile)) {
            return new VerificationResult(false, '手机号格式不正确');
        }

        if (!NoticeSceneEnum::isValid($sceneCode)) {
            return new VerificationResult(false, '验证码场景不存在');
        }

        $scene = NoticeTenantRepository::scenes($this->executionContext, $context, 'notice.verification.verify')
            ->where('code', $sceneCode)->findOrEmpty();
        if ($scene->isEmpty()) {
            return new VerificationResult(false, '验证码场景不存在');
        }

        return $this->transactions->run(function () use ($context, $scene, $mobile, $code): VerificationResult {
            $log = NoticeTenantRepository::logs($this->executionContext, $context, 'notice.verification.verify')
                ->where('scene_id', (int) $scene->id)
                ->where('channel', NoticeTenantRepository::LOG_CHANNEL_SMS)
                ->where('receiver', $mobile)
                ->where('status', NoticeTenantRepository::LOG_STATUS_SUCCESS)
                ->order('send_time', 'desc')
                ->order('id', 'desc')
                ->lock(true)
                ->findOrEmpty();

            if ($log->isEmpty() || (int)$log->is_verified === NoticeTenantRepository::LOG_VERIFIED_YES) {
                return new VerificationResult(false, '验证码不存在或已使用');
            }

            $log->check_count = (int) $log->check_count + 1;
            if ((int) $log->send_time < time() - self::VALID_PERIOD) {
                $log->save();
                return new VerificationResult(false, '验证码已过期');
            }

            if (!VerificationCodeSecret::matches($code, (string)$log->verify_code_hash)) {
                $log->save();
                return new VerificationResult(false, '验证码不正确');
            }

            $log->is_verified = NoticeTenantRepository::LOG_VERIFIED_YES;
            $log->verified_time = time();
            $log->save();
            return new VerificationResult(true);
        });
    }

    /**
     * Commits one durable send reservation before any Provider side effect.
     * A database unique key serializes all scenes for the same Tenant/mobile;
     * only an expired or explicitly failed reservation can release the slot.
     *
     * @return array{execute:bool,reason:string,log:\app\Modules\Official\Notification\Model\NoticeLog}
     */
    private function reserve(
        TenantContext|TenantSystemContext $context,
        int $tenantId,
        int $sceneId,
        string $sceneName,
        string $sceneCode,
        string $mobile,
        string $content,
        string $templateId,
        string $code,
    ): array
    {
        $idempotencyHash = hash('sha256', implode("\0", [
            'notice.verification.send',
            (string)$tenantId,
            $this->executionContext->requestId(),
        ]));
        $requestDigest = hash('sha256', implode("\0", [$sceneCode, $mobile]));
        $receiverHash = hash('sha256', $mobile);
        $create = function () use (
            $context,
            $sceneId,
            $sceneName,
            $mobile,
            $content,
            $templateId,
            $code,
            $idempotencyHash,
            $requestDigest,
            $receiverHash,
        ): array {
            $existing = NoticeTenantRepository::logs($this->executionContext, $context, 'notice.verification.send')
                ->where('idempotency_key_hash', $idempotencyHash)
                ->lock(true)
                ->findOrEmpty();
            if (!$existing->isEmpty()) {
                $reason = hash_equals((string)$existing->request_digest, $requestDigest)
                    ? 'replay'
                    : 'conflict';
                return ['execute' => false, 'reason' => $reason, 'log' => $existing];
            }

            $active = NoticeTenantRepository::logs($this->executionContext, $context, 'notice.verification.send')
                ->where('channel', NoticeTenantRepository::LOG_CHANNEL_SMS)
                ->where('receiver_hash', $receiverHash)
                ->where('reservation_active', 1)
                ->lock(true)
                ->findOrEmpty();
            $reservationTime = time();
            if (!$active->isEmpty() && (int)$active->reservation_until > $reservationTime) {
                return ['execute' => false, 'reason' => 'active', 'log' => $active];
            }
            if (!$active->isEmpty()) {
                $active->reservation_active = null;
                $active->save();
            }

            $log = NoticeTenantRepository::createLog($this->executionContext, $context, [
                'template_id' => 0,
                'scene_id' => $sceneId,
                'channel' => NoticeTenantRepository::LOG_CHANNEL_SMS,
                'receiver' => $mobile,
                'title' => $sceneName,
                'content' => $content,
                'status' => NoticeTenantRepository::LOG_STATUS_PENDING,
                'error' => '',
                'extra' => $this->encodeExtra($templateId, []),
                'send_time' => $reservationTime,
                'verify_code_hash' => VerificationCodeSecret::hash($code),
                'is_verified' => NoticeTenantRepository::LOG_VERIFIED_NO,
                'check_count' => 0,
                'verified_time' => 0,
                'provider' => '',
                'reservation_key' => 'smsr_' . bin2hex(random_bytes(16)),
                'idempotency_key_hash' => $idempotencyHash,
                'request_digest' => $requestDigest,
                'receiver_hash' => $receiverHash,
                'reservation_until' => $reservationTime + self::SEND_INTERVAL,
                'reservation_active' => 1,
            ], 'notice.verification.send');
            return ['execute' => true, 'reason' => 'owner', 'log' => $log];
        };

        try {
            return $this->transactions->run($create);
        } catch (\Throwable $exception) {
            if (!$this->isUniqueConflict($exception)) {
                throw $exception;
            }
            return $this->transactions->run(function () use (
                $context,
                $idempotencyHash,
                $requestDigest,
                $receiverHash,
            ): array {
                $existing = NoticeTenantRepository::logs($this->executionContext, $context, 'notice.verification.send')
                    ->where('idempotency_key_hash', $idempotencyHash)
                    ->lock(true)
                    ->findOrEmpty();
                if (!$existing->isEmpty()) {
                    $reason = hash_equals((string)$existing->request_digest, $requestDigest)
                        ? 'replay'
                        : 'conflict';
                    return ['execute' => false, 'reason' => $reason, 'log' => $existing];
                }
                $active = NoticeTenantRepository::logs($this->executionContext, $context, 'notice.verification.send')
                    ->where('channel', NoticeTenantRepository::LOG_CHANNEL_SMS)
                    ->where('receiver_hash', $receiverHash)
                    ->where('reservation_active', 1)
                    ->lock(true)
                    ->findOrEmpty();
                if ($active->isEmpty()) {
                    throw new \RuntimeException('SMS_RESERVATION_CONFLICT_UNRESOLVED');
                }
                return ['execute' => false, 'reason' => 'active', 'log' => $active];
            });
        }
    }

    /** Marks the side-effect boundary before control crosses into the Provider transport. */
    private function markProviderAttemptStarted(
        TenantContext|TenantSystemContext $context,
        string $reservationKey,
        string $provider,
    ): void {
        $this->transactions->run(function () use ($context, $reservationKey, $provider): void {
            $log = $this->lockedReservation($context, $reservationKey);
            if ((int)$log->status !== NoticeTenantRepository::LOG_STATUS_PENDING) {
                throw new \LogicException('SMS_RESERVATION_NOT_PENDING');
            }
            $log->provider = trim($provider);
            $log->status = NoticeTenantRepository::LOG_STATUS_UNKNOWN;
            $log->error = '短信服务商调用结果待确认';
            $log->save();
        });
    }

    /** Finalizes one reservation; only explicit failure releases the active window early. */
    private function finalizeReservation(
        TenantContext|TenantSystemContext $context,
        string $reservationKey,
        string $outcome,
        string $provider,
        string $error,
        string $templateId,
        array $receipt,
    ): void {
        $this->transactions->run(function () use (
            $context,
            $reservationKey,
            $outcome,
            $provider,
            $error,
            $templateId,
            $receipt,
        ): void {
            $log = $this->lockedReservation($context, $reservationKey);
            $currentStatus = (int)$log->status;
            if (!in_array($currentStatus, [
                NoticeTenantRepository::LOG_STATUS_PENDING,
                NoticeTenantRepository::LOG_STATUS_UNKNOWN,
            ], true)) {
                throw new \LogicException('SMS_RESERVATION_ALREADY_FINALIZED');
            }
            if ($outcome === SmsDriverResult::OUTCOME_SUCCEEDED
                && $currentStatus !== NoticeTenantRepository::LOG_STATUS_UNKNOWN) {
                throw new \LogicException('SMS_PROVIDER_ATTEMPT_NOT_RECORDED');
            }

            $log->provider = trim($provider) !== '' ? trim($provider) : (string)$log->provider;
            $log->status = match ($outcome) {
                SmsDriverResult::OUTCOME_SUCCEEDED => NoticeTenantRepository::LOG_STATUS_SUCCESS,
                SmsDriverResult::OUTCOME_FAILED => NoticeTenantRepository::LOG_STATUS_FAIL,
                default => NoticeTenantRepository::LOG_STATUS_UNKNOWN,
            };
            $log->error = $error;
            $log->extra = $this->encodeExtra($templateId, $receipt);
            $log->reservation_active = $outcome === SmsDriverResult::OUTCOME_FAILED ? null : 1;
            $log->save();
        });
    }

    private function lockedReservation(
        TenantContext|TenantSystemContext $context,
        string $reservationKey,
    ): \app\Modules\Official\Notification\Model\NoticeLog {
        $log = NoticeTenantRepository::logs($this->executionContext, $context, 'notice.verification.send')
            ->where('reservation_key', $reservationKey)
            ->lock(true)
            ->findOrEmpty();
        if ($log->isEmpty()) {
            throw new \LogicException('SMS_RESERVATION_NOT_FOUND');
        }
        return $log;
    }

    private function reservedDelivery(
        \app\Modules\Official\Notification\Model\NoticeLog $log,
        string $reason,
    ): DeliveryResult {
        if ($reason === 'conflict') {
            return new DeliveryResult(false, '', '短信幂等请求内容冲突');
        }
        if ($reason === 'active') {
            $error = (int)$log->status === NoticeTenantRepository::LOG_STATUS_UNKNOWN
                ? '上次短信发送结果未知，请稍后重试'
                : '同一手机号1分钟只能发送1条短信';
            return new DeliveryResult(false, (string)$log->provider, $error);
        }

        $status = (int)$log->status;
        $receipt = $this->decodeReceipt((string)$log->extra);
        return match ($status) {
            NoticeTenantRepository::LOG_STATUS_SUCCESS => new DeliveryResult(true, (string)$log->provider, '', $receipt),
            NoticeTenantRepository::LOG_STATUS_FAIL => new DeliveryResult(false, (string)$log->provider, (string)$log->error, $receipt),
            NoticeTenantRepository::LOG_STATUS_UNKNOWN => new DeliveryResult(false, (string)$log->provider, '短信服务商调用结果未知，请稍后重试', $receipt),
            default => new DeliveryResult(false, (string)$log->provider, '验证码发送正在处理中'),
        };
    }

    /** @param array<string,mixed> $result */
    private function normalizeOutcome(array $result): string
    {
        $outcome = (string)($result['outcome'] ?? '');
        if (!in_array($outcome, [
            SmsDriverResult::OUTCOME_SUCCEEDED,
            SmsDriverResult::OUTCOME_FAILED,
            SmsDriverResult::OUTCOME_UNKNOWN,
        ], true)) {
            return SmsDriverResult::OUTCOME_UNKNOWN;
        }
        if (($result['success'] ?? null) !== ($outcome === SmsDriverResult::OUTCOME_SUCCEEDED)) {
            return SmsDriverResult::OUTCOME_UNKNOWN;
        }
        return $outcome;
    }

    /** @return array<string,mixed> */
    private function decodeReceipt(string $extra): array
    {
        $decoded = json_decode($extra, true);
        return is_array($decoded['provider_result'] ?? null) ? $decoded['provider_result'] : [];
    }

    private function isUniqueConflict(\Throwable $exception): bool
    {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            if ((string)$current->getCode() === '23000'
                || str_contains(strtolower($current->getMessage()), 'duplicate entry')) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string,string> $variables */
    private function render(string $content, array $variables): string
    {
        foreach ($variables as $name => $value) {
            $content = str_replace(['${' . $name . '}', '{' . $name . '}'], $value, $content);
        }
        return $content;
    }

    /** @param array<string,mixed> $result */
    private function encodeExtra(string $templateId, array $result): string
    {
        return (string) json_encode([
            'provider_template_id' => $templateId,
            'provider_result' => $result,
        ], JSON_UNESCAPED_UNICODE);
    }

    private function validMobile(string $mobile): bool
    {
        return preg_match('/^1[3-9]\d{9}$/', $mobile) === 1;
    }
}
