<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\enum\FileEnum;
use app\common\model\file\File;
use app\common\model\file\FileCate;
use app\common\service\storage\Driver;
use think\file\UploadedFile;

/**
 * 上传服务：校验扩展名白名单与大小上限后，交由存储引擎（local/qiniu/aliyun/qcloud）落盘/上云，
 * 落库 pa_file 并返回可访问 URL。引擎由 storage.default 配置决定。
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
        if (!FileEnum::isValidType($type)) {
            throw new \InvalidArgumentException('文件类型无效');
        }
        if (!in_array($source, [FileEnum::SOURCE_ADMIN, FileEnum::SOURCE_USER], true)) {
            throw new \InvalidArgumentException('上传来源无效');
        }
        if ($cid < 0) {
            throw new \InvalidArgumentException('目标分类无效');
        }
        if ($cid > 0) {
            $category = FileCate::find($cid);
            if (!$category) {
                throw new \InvalidArgumentException('目标分类不存在');
            }
            if ((int)$category->type !== $type) {
                throw new \InvalidArgumentException('上传类型与目标分类不一致');
            }
        }

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

        // 交由存储引擎上传：local 落盘 public/storage/，云引擎上传至对应 bucket
        $subDir = FileEnum::SAVE_DIR[$type];
        $driver = new Driver();
        $driver->setUploadFile($uploaded);
        if (!$driver->upload($subDir)) {
            throw new \Exception($driver->getError() ?: '文件上传失败');
        }
        $uri = $driver->buildUri($subDir);

        try {
            $file = File::create([
                'cid'       => $cid,
                'source_id' => $sourceId,
                'source'    => $source,
                'type'      => $type,
                'name'      => $name,
                'uri'       => $uri,
                'storage'   => $driver->getEngineName(),
            ]);
        } catch (\Throwable $e) {
            if (!$driver->delete($uri)) {
                throw new \RuntimeException('素材记录写入失败，且存储对象清理失败：' . ($driver->getError() ?: '未知错误'), 0, $e);
            }
            throw $e;
        }

        return [
            'id'   => $file->id,
            'cid'  => $file->cid,
            'type' => $file->type,
            'name' => $file->name,
            'uri'  => $uri,
            'storage' => $driver->getEngineName(),
            'url'  => FileService::getFileUrl($uri),
        ];
    }
}
