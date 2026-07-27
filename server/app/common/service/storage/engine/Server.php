<?php
declare(strict_types=1);

namespace app\common\service\storage\engine;

use think\file\UploadedFile;

/**
 * 存储引擎抽象基类。
 * 负责接收上传文件、生成保存文件名、暴露文件信息；
 * 具体的落盘/上云由子类 upload()/delete() 实现。
 *
 * 说明：扩展名与大小的业务校验统一在 UploadService 完成（依据 FileEnum），
 * 引擎层只关注「把这个文件存到 $saveDir/$fileName」这件事。
 */
abstract class Server
{
    /** @var UploadedFile|null 待上传的文件对象 */
    protected ?UploadedFile $file = null;

    /** @var string 生成的保存文件名（不含目录） */
    protected string $fileName = '';

    /** @var array 文件信息 ext/size/mime/name/realPath */
    protected array $fileInfo = [];

    /** @var string 错误信息 */
    protected string $error = '';

    protected function __construct()
    {
    }

    /**
     * 设置待上传文件（来自 request()->file()）
     */
    public function setUploadFile(UploadedFile $file): void
    {
        $this->file     = $file;
        $this->fileInfo = [
            'ext'      => strtolower($file->getOriginalExtension()),
            'size'     => $file->getSize(),
            'mime'     => $file->getMime(),
            'name'     => $file->getOriginalName(),
            'realPath' => $file->getRealPath(),
        ];
        $this->fileName = $this->buildSaveName();
    }

    /** 执行上传，成功返回 true，失败置 error 返回 false */
    abstract public function upload(string $saveDir): bool;

    /** 删除文件（fileName 为相对 uri，不含域名） */
    abstract public function delete(string $fileName): bool;

    /** 返回生成的保存文件名 */
    public function getFileName(): string
    {
        return $this->fileName;
    }

    /** 返回文件信息 */
    public function getFileInfo(): array
    {
        return $this->fileInfo;
    }

    protected function getRealPath(): string
    {
        return (string) ($this->fileInfo['realPath'] ?? '');
    }

    /** 返回错误信息 */
    public function getError(): string
    {
        return $this->error;
    }

    /** 生成唯一保存文件名：YmdHis + md5前5位 + 4位随机 + .ext */
    private function buildSaveName(): string
    {
        $ext = pathinfo($this->fileInfo['name'] ?? '', PATHINFO_EXTENSION);
        return date('YmdHis')
            . substr(md5($this->getRealPath()), 0, 5)
            . str_pad((string) rand(0, 9999), 4, '0', STR_PAD_LEFT)
            . ".{$ext}";
    }
}
