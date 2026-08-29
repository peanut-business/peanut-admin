<?php
declare(strict_types=1);

namespace app\common\service\runtime;

/** Stable, non-secret namespace shared by every replica of one application instance. */
final readonly class RuntimeNamespace
{
    private const RESOURCE_PATTERN = '/^[a-z0-9][a-z0-9._-]{2,127}$/D';

    private function __construct(private string $resourceId)
    {
    }

    public static function fromEnvironment(): self
    {
        return self::fromResourceId((string)env('PEANUT_DATABASE_RESOURCE_ID', ''));
    }

    public static function fromResourceId(string $resourceId): self
    {
        $resourceId = strtolower(trim($resourceId));
        if (preg_match(self::RESOURCE_PATTERN, $resourceId) !== 1) {
            throw new \RuntimeException('RUNTIME_NAMESPACE_RESOURCE_ID_INVALID');
        }
        return new self($resourceId);
    }

    public function fingerprint(): string
    {
        return substr(hash('sha256', $this->resourceId), 0, 24);
    }

    public function cachePrefix(): string
    {
        return 'pa:i:' . $this->fingerprint() . ':';
    }

    public function cacheTagPrefix(): string
    {
        return $this->cachePrefix() . 'tag:';
    }

    public function sessionName(): string
    {
        return 'PA_SESSION_' . strtoupper(substr($this->fingerprint(), 0, 16));
    }

    public function sessionPrefix(): string
    {
        return $this->cachePrefix() . 'session:';
    }
}
