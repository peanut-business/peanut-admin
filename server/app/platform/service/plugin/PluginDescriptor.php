<?php
declare(strict_types=1);

namespace app\platform\service\plugin;

/** @phpstan-type IdentityList list<array<string,mixed>> */
final readonly class PluginDescriptor
{
    /**
     * @param array{type:string,reference:string,sha256:string} $source
     * @param list<array<string,mixed>> $composer
     * @param list<array<string,mixed>> $npm
     * @param list<array<string,mixed>> $frontend
     * @param array<string,string> $moduleRoots
     */
    public function __construct(
        public string $key,
        public string $version,
        public array $source,
        public string $lockDigest,
        public string $manifestDigest,
        public array $composer,
        public array $npm,
        public array $frontend,
        public array $moduleRoots
    ) {
    }

    /** @return array<string,mixed> */
    public function publicIdentity(): array
    {
        return [
            'key' => $this->key,
            'version' => $this->version,
            'source' => $this->source['type'] . ':' . $this->source['reference'],
            'artifact_sha256' => $this->source['sha256'],
            'lock_digest' => $this->lockDigest,
            'status' => 'active',
        ];
    }
}
