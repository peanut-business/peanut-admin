<?php
declare(strict_types=1);

namespace app\common\service\installation;

use JsonException;
use RuntimeException;

/** Read the independent application and scaffold versions from one strict runtime contract. */
final readonly class ApplicationReleaseVersions
{
    private const KEYS = [
        'schema_version',
        'protocol',
        'product_release',
        'scaffold_template',
        'generated_application_default',
        'core_php',
        'core_web',
    ];

    private function __construct(private array $values)
    {
    }

    /** Load exactly the supported fields while accepting harmless JSON key-order differences. */
    public static function load(string $path): self
    {
        if (!is_file($path) || is_link($path)) {
            throw new RuntimeException('APPLICATION_RELEASE_VERSIONS_INVALID');
        }
        try {
            $document = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('APPLICATION_RELEASE_VERSIONS_INVALID', 0, $exception);
        }
        $keys = is_array($document) ? array_keys($document) : [];
        if (!is_array($document)
            || count($keys) !== count(self::KEYS)
            || array_diff($keys, self::KEYS) !== []
            || array_diff(self::KEYS, $keys) !== []
            || ($document['schema_version'] ?? null) !== 1
            || ($document['protocol'] ?? null) !== 'peanut.release-versions.v1'
        ) {
            throw new RuntimeException('APPLICATION_RELEASE_VERSIONS_INVALID');
        }
        $values = [];
        foreach (self::KEYS as $key) {
            $value = $document[$key];
            if (!in_array($key, ['schema_version', 'protocol'], true)
                && (!is_string($value) || preg_match(
                    '/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(?:[-+][0-9A-Za-z.-]+)?$/D',
                    $value,
                ) !== 1)) {
                throw new RuntimeException('APPLICATION_RELEASE_VERSIONS_INVALID: ' . $key);
            }
            $values[$key] = $value;
        }
        return new self($values);
    }

    public function productRelease(): string
    {
        return $this->values['product_release'];
    }

    public function scaffoldTemplate(): string
    {
        return $this->values['scaffold_template'];
    }

    /** @return array<string,int|string> */
    public function toArray(): array
    {
        return $this->values;
    }
}
