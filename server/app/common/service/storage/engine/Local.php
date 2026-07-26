<?php
declare(strict_types=1);

namespace app\common\service\storage\engine;

use think\facade\Filesystem;

/**
 * 本地磁盘存储引擎（peanut-v2 默认）。
 * 落盘到 public/storage/<saveDir>/<fileName>，对外 uri 带 storage/ 前缀，
 * 由 nginx / think-filesystem 的 /storage 映射直接访问。
 */
class Local extends Server
{
    public function __construct()
    {
        parent::__construct();
    }

    public function upload(string $saveDir): bool
    {
        try {
            $saved = Filesystem::disk('public')->putFileAs($saveDir, $this->file, $this->fileName);
            if (!$saved) {
                $this->error = '文件保存失败';
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    /** 删除本地文件；fileName 为相对 uri，如 storage/uploads/images/xxx.png */
    public function delete(string $fileName): bool
    {
        $path = public_path() . ltrim($fileName, '/');
        return !file_exists($path) ?: @unlink($path);
    }
}
