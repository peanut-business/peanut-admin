<?php
declare(strict_types=1);

namespace app\Modules\Official\ImportExport\Infrastructure\Configuration;

use app\common\persistence\CoreTenantRepositoryFactory;
use app\platform\service\module\PdoModuleGovernanceProvider;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\PlatformContext;
use PeanutAdmin\Settings\Application\SettingAdminService;
use PeanutAdmin\Settings\Application\SettingException;
use PeanutAdmin\Settings\Definition\SettingDefinition;
use PeanutAdmin\Settings\Definition\SettingDefinitionLoader;
use PeanutAdmin\Settings\Definition\SettingDefinitionRegistry;
use PeanutAdmin\Settings\Secret\SecretProtector;
use think\facade\Config;

/** Transfers deployment-scoped values through the Core Settings contract. */
final readonly class CoreSettingsConfigurationAdapter implements ConfigurationTransferAdapter
{
    private ?SettingDefinitionRegistry $providedDefinitions;
    private SecretProtector $protector;

    /**
     * The second argument accepts either a trusted definition registry or a
     * protector for convenient Host assembly. A missing protector is explicit
     * and remains fail-closed when a configured secret is applied.
     */
    public function __construct(
        private PDO $pdo,
        SettingDefinitionRegistry|SecretProtector|null $definitions = null,
        ?SecretProtector $protector = null,
    ) {
        $this->providedDefinitions = $definitions instanceof SettingDefinitionRegistry ? $definitions : null;
        $this->protector = $definitions instanceof SecretProtector && $protector === null
            ? $definitions
            : ($protector ?? new UnavailableSecretProtector());
    }

    public function key(): string
    {
        return ConfigurationPackageCodec::ADAPTER_CORE_SETTINGS;
    }

    public function supportsCreate(): bool
    {
        return true;
    }

    public function export(TenantContext|PlatformContext $context): array
    {
        $this->platformContext($context);
        $definitions = $this->definitions();
        if ($definitions === []) {
            return [];
        }

        $repository = $this->settings();
        $entries = [];
        foreach ($definitions as $definition) {
            $snapshot = $repository->deploymentSnapshot($definition);
            $row = $snapshot['deployment'];
            if (!is_array($row)) {
                continue;
            }
            $qualifiedKey = $definition->qualifiedKey();
            $state = (string)($row['value_state'] ?? '');
            if (!in_array($state, ['set', 'unset'], true)) {
                throw new \RuntimeException('TRANSFER_CORE_SETTING_INVALID');
            }
            $value = $definition->secret
                ? $this->secretMarker($qualifiedKey, $state === 'set' ? 'configured' : 'unconfigured')
                : ($state === 'set' ? $this->decodeValue($row['value_json'] ?? null) : null);
            $entries[] = $definition->secret
                ? $this->secretEntry($qualifiedKey, $value)
                : ConfigurationTransferValue::entry($this->key(), $qualifiedKey, $value);
        }

        return $entries;
    }

    public function current(TenantContext|PlatformContext $context, string $key): array
    {
        $this->platformContext($context);
        [$moduleKey, $settingKey] = $this->splitKey($key);
        $definition = $this->definition($this->definitions(), $moduleKey, $settingKey);
        if (!$definition->allows('deployment')) {
            throw new \RuntimeException('TRANSFER_CORE_SETTING_SCOPE_INVALID');
        }

        $snapshot = $this->settings()->deploymentSnapshot($definition);
        $row = $snapshot['deployment'];
        if (!is_array($row)) {
            return ['exists' => false, 'value' => null, 'revision' => null];
        }
        $state = (string)$row['value_state'];
        if (!in_array($state, ['set', 'unset'], true)) {
            throw new \RuntimeException('TRANSFER_CORE_SETTING_INVALID');
        }
        $value = $definition->secret
            ? $this->secretMarker($key, $state === 'set' ? 'configured' : 'unconfigured')
            : ($state === 'set' ? $this->decodeValue($row['value_json'] ?? null) : null);

        return [
            'exists' => true,
            'value' => $definition->secret
                ? $this->secretEntry($key, $value)['value']
                : ConfigurationTransferValue::entry($this->key(), $key, $value)['value'],
            'revision' => (int)($row['revision'] ?? 0),
        ];
    }

    public function apply(
        TenantContext|PlatformContext $context,
        string $key,
        mixed $value,
        array $entry,
        ?int $revision,
    ): void {
        $platform = $this->platformContext($context);
        [$moduleKey, $settingKey] = $this->splitKey($key);
        $definition = $this->definition($this->definitions(), $moduleKey, $settingKey);
        if (!$definition->allows('deployment')) {
            throw new \RuntimeException('TRANSFER_CORE_SETTING_SCOPE_INVALID');
        }

        $isUnsetSecret = $definition->secret
            && SecretReferenceCodec::isMarker($entry['value'] ?? null)
            && (($entry['value']['$secret']['state'] ?? null) === 'unconfigured');
        $current = $this->current($platform, $key);
        if ($isUnsetSecret || $value === null) {
            if (!$current['exists'] || !is_int($revision)) {
                return;
            }
            $this->admin()->unsetDeployment(
                $definition,
                $platform->operatorId,
                $this->now(),
                self::etag($revision),
            );
            return;
        }

        try {
            $definition->assertValue($value);
            $this->admin()->replaceDeployment(
                $definition,
                $value,
                $platform->operatorId,
                $this->now(),
                null,
                $current['exists'] && is_int($revision) ? self::etag($revision) : null,
                $current['exists'] ? null : '*',
            );
        } catch (SettingException $exception) {
            if ($exception->errorCode === 'SETTING_SECRET_UNAVAILABLE') {
                throw new \RuntimeException('TRANSFER_SECRET_PROTECTOR_UNAVAILABLE', 0, $exception);
            }
            throw $exception;
        }
    }

    private function admin(): SettingAdminService
    {
        return new SettingAdminService($this->settings(), $this->protector);
    }

    private function settings(): \PeanutAdmin\Settings\Persistence\PdoSettingRepository
    {
        return (new CoreTenantRepositoryFactory($this->pdo))->settings();
    }

    /** @return list<SettingDefinition> */
    private function definitions(): array
    {
        $registry = $this->providedDefinitions;
        if (!$registry instanceof SettingDefinitionRegistry) {
            try {
                $moduleConfig = Config::get('modules', []);
                if (!is_array($moduleConfig)) {
                    throw new \RuntimeException('TRANSFER_CORE_SETTINGS_UNAVAILABLE');
                }
                $compiled = PdoModuleGovernanceProvider::forApplication($this->pdo)->registry()->compiled();
                $loader = new SettingDefinitionLoader();
                $registry = new SettingDefinitionRegistry();
                foreach ($compiled->modules as $manifest) {
                    $moduleKey = (string)($manifest->data['key'] ?? '');
                    $backend = is_array($manifest->data['backend'] ?? null)
                        ? $manifest->data['backend']
                        : [];
                    $resource = $backend['setting_definitions'] ?? null;
                    $loaded = is_string($resource)
                        ? $loader->load($moduleKey, $manifest->root . '/' . ltrim($resource, '/'))
                        : [];
                    $registry->registerModule($moduleKey, $loaded);
                }
            } catch (\Throwable $exception) {
                throw new \RuntimeException('TRANSFER_CORE_SETTINGS_UNAVAILABLE', 0, $exception);
            }
        }

        return array_values(array_filter(
            $registry->all(),
            static fn(SettingDefinition $definition): bool => $definition->allows('deployment'),
        ));
    }

    /** @param list<SettingDefinition> $definitions */
    private function definition(SettingDefinition|array $definitions, string $moduleKey, string $settingKey): SettingDefinition
    {
        if ($definitions instanceof SettingDefinition) {
            return $definitions;
        }
        foreach ($definitions as $definition) {
            if ($definition->moduleKey === $moduleKey && $definition->key === $settingKey) {
                return $definition;
            }
        }
        throw new \RuntimeException('TRANSFER_CORE_SETTING_NOT_FOUND');
    }

    private function platformContext(TenantContext|PlatformContext $context): PlatformContext
    {
        if (!$context instanceof PlatformContext
            || $context->accountId < 1
            || $context->operatorId < 1
            || $context->sessionKey === ''
            || $context->clientKey === ''
            || $context->requestId === '') {
            throw new \RuntimeException('TRANSFER_DEPLOYMENT_CONTEXT_INVALID');
        }
        return $context;
    }

    /** @return array{0:string,1:string} */
    private function splitKey(string $key): array
    {
        $parts = explode(':', $key, 2);
        if (count($parts) !== 2
            || preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*(?:\.[a-z][a-z0-9]*(?:-[a-z0-9]+)*)*$/D', $parts[0]) !== 1
            || preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/D', $parts[1]) !== 1) {
            throw new \RuntimeException('TRANSFER_CORE_SETTING_INVALID');
        }
        return [$parts[0], $parts[1]];
    }

    private function decodeValue(mixed $encoded): mixed
    {
        try {
            return json_decode((string)$encoded, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \RuntimeException('TRANSFER_CORE_SETTING_INVALID');
        }
    }

    /** @return array{\$secret:array{state:string,reference:string,shape:string}} */
    private function secretMarker(string $key, string $state): array
    {
        $references = [];
        return SecretReferenceCodec::marker(
            $state === 'configured' ? 'configured' : '',
            ConfigurationTransferValue::referenceRoot($this->key(), $key),
            $references,
        );
    }

    /** @param array{\$secret:array{state:string,reference:string,shape:string}} $marker */
    private function secretEntry(string $key, array $marker): array
    {
        $references = SecretReferenceCodec::references($marker);
        return [
            'adapter' => $this->key(),
            'key' => $key,
            'value' => $marker,
            'secrets' => $references,
        ];
    }

    private function now(): DateTimeImmutable
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $microseconds = (int)$now->format('u');
        return $microseconds % 1000 === 0
            ? $now
            : $now->modify('-' . ($microseconds % 1000) . ' microseconds');
    }

    private static function etag(int $revision): string
    {
        return '"rev-' . $revision . '"';
    }
}
