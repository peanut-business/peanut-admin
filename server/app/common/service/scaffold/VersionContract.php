<?php
declare(strict_types=1);

namespace app\common\service\scaffold;

use Composer\Semver\Comparator;
use Composer\Semver\VersionParser;
use RuntimeException;

final readonly class VersionContract
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

    public static function load(string $path): self
    {
        self::loadSemver($path);
        $raw = file_get_contents($path);
        try {
            $values = is_string($raw) ? json_decode($raw, true, 512, JSON_THROW_ON_ERROR) : null;
        } catch (\JsonException $exception) {
            throw new RuntimeException('VERSION_CONTRACT_INVALID_JSON', 0, $exception);
        }
        if (!is_array($values) || array_keys($values) !== self::KEYS
            || ($values['schema_version'] ?? null) !== 1
            || ($values['protocol'] ?? null) !== 'peanut.release-versions.v1') {
            throw new RuntimeException('VERSION_CONTRACT_SCHEMA_INVALID');
        }
        $parser = new VersionParser();
        foreach (array_slice(self::KEYS, 2) as $key) {
            if (!is_string($values[$key]) || $values[$key] === '') {
                throw new RuntimeException('VERSION_CONTRACT_VERSION_INVALID: ' . $key);
            }
            try {
                $parser->normalize($values[$key]);
            } catch (\UnexpectedValueException $exception) {
                throw new RuntimeException('VERSION_CONTRACT_VERSION_INVALID: ' . $key, 0, $exception);
            }
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

    public function generatedApplicationDefault(): string
    {
        return $this->values['generated_application_default'];
    }

    public function corePhp(): string
    {
        return $this->values['core_php'];
    }

    public function coreWeb(): string
    {
        return $this->values['core_web'];
    }

    public function assertSame(string $actual, string $expected, string $error): void
    {
        $parser = new VersionParser();
        try {
            $normalizedActual = $parser->normalize($actual);
            $normalizedExpected = $parser->normalize($expected);
        } catch (\UnexpectedValueException $exception) {
            throw new RuntimeException($error, 0, $exception);
        }
        if (!Comparator::equalTo($normalizedActual, $normalizedExpected)) {
            throw new RuntimeException($error . ": expected {$expected}, got {$actual}");
        }
    }

    public function assertValid(string $version, string $error): void
    {
        try {
            (new VersionParser())->normalize($version);
        } catch (\UnexpectedValueException $exception) {
            throw new RuntimeException($error, 0, $exception);
        }
    }

    /** @return array<string,int|string> */
    public function toArray(): array
    {
        return $this->values;
    }

    private static function loadSemver(string $contractPath): void
    {
        if (class_exists(VersionParser::class)) {
            return;
        }
        $root = dirname($contractPath);
        $autoload = $root . '/server/vendor/autoload.php';
        if (!is_file($autoload)) {
            throw new RuntimeException('VERSION_CONTRACT_COMPOSER_AUTOLOAD_UNAVAILABLE');
        }
        require_once $autoload;
    }
}
