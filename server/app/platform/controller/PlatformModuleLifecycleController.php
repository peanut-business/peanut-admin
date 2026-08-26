<?php
declare(strict_types=1);

namespace app\platform\controller;

use app\common\service\JsonService;
use app\platform\service\PlatformRuntimeFactory;
use app\platform\service\plugin\DeterministicTarArchive;
use app\platform\service\plugin\PluginLifecycleException;
use app\platform\service\plugin\PluginPackageException;
use PeanutAdmin\Kernel\Module\ModuleException;

final class PlatformModuleLifecycleController extends BasePlatformController
{
    public function lists()
    {
        try {
            $page = $this->positiveInteger($this->request->get('page', 1));
            $pageSize = $this->positiveInteger($this->request->get('page_size', 20));
            if ($pageSize > 100) throw new PluginLifecycleException('PAGE_SIZE_INVALID', 'Page size is invalid.');
            $moduleKey = trim((string)$this->request->get('module_key', ''));
            $result = PlatformRuntimeFactory::moduleRuntime()->modules($page, $pageSize, $moduleKey === '' ? null : $moduleKey);
            return $this->dataLists($result['items'], $result['total'], $page, $pageSize);
        } catch (PluginLifecycleException $exception) {
            return $this->domainFailure($exception);
        } catch (\Throwable) {
            return $this->unavailable();
        }
    }

    public function install()
    {
        try {
            $uploaded = $this->request->file('package');
            $expected = strtolower(trim((string)$this->request->post('expected_sha256', '')));
            $keyId = trim((string)$this->request->post('signature_key_id', ''));
            if (!$uploaded || strtolower((string)$uploaded->getOriginalExtension()) !== 'tar'
                || $uploaded->getSize() <= 0 || $uploaded->getSize() > DeterministicTarArchive::MAX_TOTAL_BYTES
                || preg_match('/^[a-f0-9]{64}$/D', $expected) !== 1) {
                throw new PluginPackageException('MODULE_PACKAGE_REQUEST_INVALID', 'Module package request is invalid.');
            }
            return $this->data(PlatformRuntimeFactory::moduleRuntime()->install(
                $uploaded->getPathname(),
                $expected,
                $keyId === '' ? null : $keyId,
            ));
        } catch (PluginPackageException|PluginLifecycleException|ModuleException $exception) {
            return $this->domainFailure($exception);
        } catch (\Throwable) {
            return $this->unavailable();
        }
    }

    public function uninstall()
    {
        try {
            $params = $this->request->post();
            $moduleKey = $this->moduleKey($params['module_key'] ?? null);
            $purge = $this->boolean($params['purge'] ?? false, 'purge');
            $preview = $this->boolean($params['preview'] ?? null, 'preview');
            if ($preview) return $this->data(PlatformRuntimeFactory::moduleRuntime()->uninstallPreview($moduleKey, $purge));
            $this->changeReason($params['change_reason'] ?? null);
            $plan = $params['confirm_plan'] ?? null;
            $digest = strtolower(trim((string)($params['confirm_plan_digest'] ?? '')));
            $packageKey = trim((string)($params['confirm_package_key'] ?? ''));
            if (!is_array($plan) || array_is_list($plan) || preg_match('/^[a-f0-9]{64}$/D', $digest) !== 1
                || $packageKey === '' || ($plan['package_key'] ?? null) !== $packageKey) {
                throw new PluginLifecycleException('MODULE_UNINSTALL_PLAN_CHANGED', 'Module uninstall confirmation is invalid.');
            }
            return $this->data(PlatformRuntimeFactory::moduleRuntime()->uninstall($moduleKey, $purge, $plan, $digest));
        } catch (PluginLifecycleException|PluginPackageException|ModuleException $exception) {
            return $this->domainFailure($exception);
        } catch (\Throwable) {
            return $this->unavailable();
        }
    }

    public function disable()
    {
        try {
            $params = $this->request->post();
            $moduleKey = $this->moduleKey($params['module_key'] ?? null);
            $this->changeReason($params['change_reason'] ?? null);
            return $this->data(PlatformRuntimeFactory::moduleRuntime()->disable($moduleKey));
        } catch (PluginLifecycleException|ModuleException $exception) {
            return $this->domainFailure($exception);
        } catch (\Throwable) {
            return $this->unavailable();
        }
    }

    public function sync()
    {
        try {
            $moduleKey = trim((string)$this->request->post('module_key', ''));
            if ($moduleKey !== '') $this->moduleKey($moduleKey);
            return $this->data(PlatformRuntimeFactory::moduleRuntime()->sync($moduleKey === '' ? null : $moduleKey));
        } catch (PluginLifecycleException|ModuleException $exception) {
            return $this->domainFailure($exception);
        } catch (\Throwable) {
            return $this->unavailable();
        }
    }

    private function moduleKey(mixed $value): string
    {
        $key = trim((string)$value);
        if (preg_match('/^[a-z][a-z0-9]*(?:[.-][a-z0-9]+)*$/D', $key) !== 1 || strlen($key) > 96) {
            throw new PluginLifecycleException('MODULE_KEY_INVALID', 'Module key is invalid.');
        }
        return $key;
    }

    private function boolean(mixed $value, string $field): bool
    {
        if (!is_bool($value)) throw new PluginLifecycleException('MODULE_REQUEST_INVALID', "{$field} must be boolean.");
        return $value;
    }

    private function changeReason(mixed $value): string
    {
        $reason = trim((string)$value);
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 500) throw new PluginLifecycleException('MODULE_CHANGE_REASON_INVALID', 'Change reason is invalid.');
        return $reason;
    }

    private function positiveInteger(mixed $value): int
    {
        $candidate = is_int($value) ? (string)$value : trim((string)$value);
        if (preg_match('/^[1-9][0-9]*$/D', $candidate) !== 1) throw new PluginLifecycleException('PAGE_INVALID', 'Page is invalid.');
        return (int)$candidate;
    }

    private function domainFailure(PluginLifecycleException|PluginPackageException|ModuleException $exception)
    {
        $code = $exception->errorCode;
        $status = match (true) {
            in_array($code, ['MODULE_REGISTRY_UNAVAILABLE', 'PLUGIN_LOCK_INVALID', 'PLUGIN_ARTIFACT_MISMATCH'], true) => 50300,
            str_contains($code, 'PLAN_CHANGED'), str_contains($code, 'CONFLICT'), str_contains($code, 'DEPENDENT'),
                str_contains($code, 'TENANT_MODULE_ACTIVE'), str_contains($code, 'STATE'), str_contains($code, 'IN_PROGRESS'),
                $code === 'MODULE_UNINSTALL_BLOCKED' => 40900,
            default => 42200,
        };
        return JsonService::fail('Module runtime request was rejected.', ['error_code' => $code], $status);
    }

    private function unavailable()
    {
        return JsonService::fail('Module registry is unavailable.', ['error_code' => 'MODULE_REGISTRY_UNAVAILABLE'], 50300);
    }
}
