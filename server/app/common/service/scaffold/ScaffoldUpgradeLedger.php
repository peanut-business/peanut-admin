<?php
declare(strict_types=1);

namespace app\common\service\scaffold;

use RuntimeException;

final class ScaffoldUpgradeLedger
{
    public function __construct(private readonly string $path)
    {
    }

    /** @param array<string,mixed> $entry */
    public function appendOnce(array $entry): array
    {
        ScaffoldPathGuard::ensureDirectory(dirname($this->path));
        $handle = fopen($this->path, 'c+b');
        if ($handle === false) {
            throw new RuntimeException('SCAFFOLD_LEDGER_OPEN_FAILED');
        }
        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('SCAFFOLD_LEDGER_LOCK_FAILED');
            }
            rewind($handle);
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                try {
                    $existing = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $exception) {
                    throw new RuntimeException('SCAFFOLD_LEDGER_CORRUPT', 0, $exception);
                }
                if (is_array($existing) && ($existing['candidate'] ?? null) === $entry['candidate']) {
                    return $existing;
                }
            }
            fseek($handle, 0, SEEK_END);
            $json = json_encode($entry, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            if (fwrite($handle, $json . "\n") === false || !fflush($handle)) {
                throw new RuntimeException('SCAFFOLD_LEDGER_APPEND_FAILED');
            }
            return $entry;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
