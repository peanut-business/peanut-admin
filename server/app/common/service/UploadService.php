<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\enum\FileEnum;
use app\common\model\file\File;
use think\facade\Filesystem;
use think\file\UploadedFile;

/**
 * 上传服务：本地磁盘引擎（likeadmin 默认）。
 * 校验扩展名白名单与大小上限，保存到 public/uploads/<type>/<Ymd>/，
 * 落库 pa_file 并返回可访问 URL。
 */
class UploadService
{
    public static function image(int $cid, int $sourceId = 0, int $source = FileEnum::SOURCE_ADMIN): array
    {
        return self::save(FileEnum::IMAGE, $cid, $sourceId, $source);
    }

    public static function video(int $cid, int $sourceId = 0, int $source = FileEnum::SOURCE_ADMIN): array
    {
        return self::save(FileEnum::VIDEO, $cid, $sourceId, $source);
    }

    public static function file(int $cid, int $sourceId = 0, int $source = FileEnum::SOURCE_ADMIN): array
    {
        return self::save(FileEnum::FILE, $cid, $sourceId, $source);
    }

    /**
     * @param int $type FileEnum::IMAGE|VIDEO|FILE
     * @throws \Exception 校验失败
     */
    protected static function save(int $type, int $cid, int $sourceId, int $source): array
    {
        /** @var UploadedFile|null $uploaded */
        $uploaded = request()->file('file');
        if (!$uploaded) {
            throw new \Exception('未接收到上传文件');
        }

        // 扩展名白名单
        $ext = strtolower($uploaded->getOriginalExtension());
        if (!in_array($ext, FileEnum::EXT[$type], true)) {
            throw new \Exception('不允许上传 ' . ($ext ?: '未知') . ' 格式文件');
        }

        // 大小上限
        if ($uploaded->getSize() > FileEnum::MAX_SIZE[$type]) {
            $mb = (int)(FileEnum::MAX_SIZE[$type] / 1024 / 1024);
            throw new \Exception('文件大小超过上限 ' . $mb . 'MB');
        }

        // 原始文件名（去扩展名，限长 128）
        $originName = $uploaded->getOriginalName();
        $name = mb_substr((string)pathinfo($originName, PATHINFO_FILENAME), 0, 120) . '.' . $ext;

        // 保存到 public/storage/uploads/<type>/<Ymd>/<hash>.ext
        // putFile 默认规则已生成 date('Ymd')/md5 前缀，此处只需给出业务子目录，避免日期重复。
        $subDir  = FileEnum::SAVE_DIR[$type];
        $saved   = Filesystem::disk('public')->putFile($subDir, $uploaded);
        if (!$saved) {
            throw new \Exception('文件保存失败');
        }
        // Filesystem 存到 public/storage/<subDir>/xxx；对外 uri 走 storage 目录
        $uri = 'storage/' . str_replace('\\', '/', $saved);

        $file = File::create([
            'cid'       => $cid,
            'source_id' => $sourceId,
            'source'    => $source,
            'type'      => $type,
            'name'      => $name,
            'uri'       => $uri,
        ]);

        return [
            'id'   => $file->id,
            'cid'  => $file->cid,
            'type' => $file->type,
            'name' => $file->name,
            'uri'  => $uri,
            'url'  => FileService::getFileUrl($uri),
        ];
    }
}