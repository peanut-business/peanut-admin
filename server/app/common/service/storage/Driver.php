<?php
declare(strict_types=1);

namespace app\common\service\storage;

use app\common\service\ConfigService;
use app\common\service\storage\engine\Server;
use think\file\UploadedFile;

/**
 * 存储模块驱动：按 storage.default 选择引擎并委派上传/删除。
 * 引擎配置从 pa_config(type=storage) 读取（云引擎的 value 为 JSON，需显式 decode）。
 */
class Driver
{
    private string $engineName;
    private Server $engine;

    /**
     * @param string|null $storage 指定引擎，null 则用系统默认
     * @throws \Exception 引擎不存在
     */
    public function __construct(?string $storage = null)
    {
        $this->engineName = $storage ?: (string) ConfigService::get('storage', 'default', 'local');
        $this->engine     = $this->makeEngine($this->engineName);
    }

    public function getEngineName(): string
    {
        return $this->engineName;
    }

    public function setUploadFile(UploadedFile $file): void
    {
        $this->engine->setUploadFile($file);
    }

    public function upload(string $saveDir): bool
    {
        return $this->engine->upload($saveDir);
    }

    public function delete(string $fileName): bool
    {
        return $this->engine->delete($fileName);
    }

    public function getFileName(): string
    {
        return $this->engine->getFileName();
    }

    public function getFileInfo(): array
    {
        return $this->engine->getFileInfo();
    }

    public function getError(): string
    {
        return $this->engine->getError();
    }

    /**
     * 构建对外可访问 uri：
     * - local：storage/<saveDir>/<fileName>（带前缀，走 /storage 映射）
     * - 云引擎：<saveDir>/<fileName>（不带前缀，FileService 拼云端 domain）
     */
    public function buildUri(string $saveDir): string
    {
        $key = trim($saveDir, '/') . '/' . $this->engine->getFileName();
        return $this->engineName === 'local' ? 'storage/' . $key : $key;
    }

    /**
     * @throws \Exception
     */
    private function makeEngine(string $engineName): Server
    {
        $class = __NAMESPACE__ . '\\engine\\' . ucfirst($engineName);
        if (!class_exists($class)) {
            throw new \Exception('未找到存储引擎: ' . $engineName);
        }
        if ($engineName === 'local') {
            return new $class();
        }
        return new $class(self::engineConfig($engineName));
    }

    /** 读取某云引擎的配置（JSON 字符串 → 数组） */
    public static function engineConfig(string $engineName): array
    {
        $raw = ConfigService::get('storage', $engineName, '');
        if (is_array($raw)) {
            return $raw;
        }
        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
