<?php
declare(strict_types=1);

namespace app\common\service\runtime;

use app\common\execution\CurrentExecutionContext;
use app\common\service\audit\RedactionPolicy;
use think\facade\Log;

/** Adds execution trace and redaction to every application-owned runtime log. */
final class OperationalLog
{
    /** @param array<string,mixed> $attributes */
    public static function info(string $event, array $attributes = []): void
    {
        self::write('info', $event, $attributes);
    }

    /** @param array<string,mixed> $attributes */
    public static function notice(string $event, array $attributes = []): void
    {
        self::write('notice', $event, $attributes);
    }

    /** @param array<string,mixed> $attributes */
    public static function warning(string $event, array $attributes = []): void
    {
        self::write('warning', $event, $attributes);
    }

    /** @param array<string,mixed> $attributes */
    public static function error(string $event, array $attributes = []): void
    {
        self::write('error', $event, $attributes);
    }

    /** @param array<string,mixed> $attributes */
    private static function write(string $level, string $event, array $attributes): void
    {
        try {
            Log::$level(RedactionPolicy::encode([
                'event' => self::event($event),
                'attributes' => self::attributes($attributes),
            ]));
        } catch (\Throwable) {
            // Runtime diagnostics must never replace the product operation.
        }
    }

    /** @param array<string,mixed> $attributes @return array<string,mixed> */
    private static function attributes(array $attributes): array
    {
        try {
            $context = app(CurrentExecutionContext::class)->current();
        } catch (\Throwable) {
            $context = null;
        }
        $trace = $context === null ? [] : [
            'request_id' => $context->requestId,
            'operation' => $context->operation,
            'actor_type' => $context->actorType,
            'tenant_id' => $context->tenantId(),
        ];
        $trace['runtime_id'] = RuntimeNamespace::fromEnvironment()->fingerprint();

        $sanitized = RedactionPolicy::sanitize($trace + $attributes);
        return is_array($sanitized) ? $sanitized : [];
    }

    private static function event(string $event): string
    {
        $event = trim($event);
        if (preg_match('/^[a-z][a-z0-9_.-]{0,127}$/D', $event) !== 1) {
            throw new \InvalidArgumentException('OPERATIONAL_LOG_EVENT_INVALID');
        }
        return $event;
    }

    private function __construct()
    {
    }
}
