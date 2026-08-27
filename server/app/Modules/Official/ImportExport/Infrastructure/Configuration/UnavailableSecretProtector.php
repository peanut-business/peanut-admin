<?php
declare(strict_types=1);

namespace app\Modules\Official\ImportExport\Infrastructure\Configuration;

use PeanutAdmin\Settings\Secret\SecretProtector;
use PeanutAdmin\Settings\Secret\SecretStorageContext;
use PeanutAdmin\Settings\Application\SettingException;

/** Explicit fail-closed protector used when deployment secret material is absent. */
final class UnavailableSecretProtector implements SecretProtector
{
    public function protect(string $plaintext, SecretStorageContext $context): array
    {
        throw self::unavailable();
    }

    public function reveal(
        string $ciphertext,
        string $nonce,
        string $keyId,
        SecretStorageContext $context,
    ): string {
        throw self::unavailable();
    }

    private static function unavailable(): SettingException
    {
        return SettingException::unavailable(
            'SETTING_SECRET_UNAVAILABLE',
            'The setting secret protector is unavailable.',
        );
    }
}
