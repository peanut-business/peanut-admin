<?php
declare(strict_types=1);

namespace app\adminapi\logic\dept;

use app\common\logic\BaseLogic;
use app\common\model\auth\AdminJobs;
use app\common\model\dept\Jobs;
use app\common\service\FileService;
use think\facade\Db;
use ZipArchive;

class JobsLogic extends BaseLogic
{
    private const EXPORT_MAX_ROWS = 25000;
    private const EXPORT_DEFAULT_NAME = '岗位列表';

    /** 将 Peanut 旧版 is_disable 请求转换为 LikeAdmin status 契约。 */
    public static function normalizeInput(array $params): array
    {
        if (!array_key_exists('status', $params) && array_key_exists('is_disable', $params)) {
            $params['status'] = (int)$params['is_disable'] === 0 ? 1 : 0;
        }
        return $params;
    }

    /**
     * 岗位分页列表；export=1 返回导出信息，export=2 生成 XLSX 并返回 URL。
     *
     * @return array|false
     */
    public static function lists(array $params): array|false
    {
        $params = self::normalizeInput($params);
        try {
            $count = self::buildListQuery($params)->count();
            $pageSize = (int)($params['page_size'] ?? 15);
            $pageSize = max(1, min(self::EXPORT_MAX_ROWS, $pageSize));

            if ((int)($params['export'] ?? 0) === 1) {
                return self::exportInfo($count, $pageSize);
            }
            if ((int)($params['export'] ?? 0) === 2) {
                return self::export($params, $count, $pageSize);
            }

            $pageNo = max(1, (int)($params['page_no'] ?? 1));
            $rows = self::buildListQuery($params)
                ->append(['status_desc'])
                ->page($pageNo, $pageSize)
                ->select()
                ->toArray();

            return [
                'lists' => self::formatRows($rows),
                'count' => $count,
                'pageNo' => $pageNo,
                'pageSize' => $pageSize,
            ];
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    /** 全部正常岗位（供选择器使用）。 */
    public static function all(): array
    {
        return Jobs::where('status', 1)
            ->field('id,name,code,status,is_disable')
            ->order(['sort' => 'desc', 'id' => 'desc'])
            ->select()
            ->toArray();
    }

    public static function detail(int $id): array
    {
        $jobs = Jobs::findOrEmpty($id);
        if ($jobs->isEmpty()) {
            return [];
        }
        return self::formatRows([$jobs->append(['status_desc'])->toArray()])[0];
    }

    public static function add(array $params): bool
    {
        $params = self::normalizeInput($params);
        Db::startTrans();
        try {
            self::assertUnique((string)$params['name'], (string)$params['code']);
            $status = (int)$params['status'];
            Jobs::create([
                'name'       => trim((string)$params['name']),
                'code'       => trim((string)$params['code']),
                'sort'       => (int)($params['sort'] ?? 0),
                'status'     => $status,
                'is_disable' => $status === 1 ? 0 : 1,
                'remark'     => (string)($params['remark'] ?? ''),
            ]);
            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function edit(array $params): bool
    {
        $params = self::normalizeInput($params);
        Db::startTrans();
        try {
            $id = (int)$params['id'];
            $jobs = Jobs::where('id', $id)->lock(true)->findOrEmpty();
            if ($jobs->isEmpty()) {
                throw new \RuntimeException('岗位不存在');
            }
            self::assertUnique((string)$params['name'], (string)$params['code'], $id);
            $status = (int)$params['status'];
            $jobs->save([
                'name'       => trim((string)$params['name']),
                'code'       => trim((string)$params['code']),
                'sort'       => (int)($params['sort'] ?? 0),
                'status'     => $status,
                'is_disable' => $status === 1 ? 0 : 1,
                'remark'     => (string)($params['remark'] ?? ''),
            ]);
            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function delete(int $id): bool
    {
        Db::startTrans();
        try {
            $jobs = Jobs::where('id', $id)->lock(true)->findOrEmpty();
            if ($jobs->isEmpty()) {
                throw new \RuntimeException('岗位不存在');
            }
            if (AdminJobs::where('jobs_id', $id)->count() > 0) {
                throw new \RuntimeException('已关联管理员，暂不可删除');
            }
            $jobs->delete();
            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function updateStatus(int $id, int $status): bool
    {
        Db::startTrans();
        try {
            $jobs = Jobs::where('id', $id)->lock(true)->findOrEmpty();
            if ($jobs->isEmpty()) {
                throw new \RuntimeException('岗位不存在');
            }
            $jobs->save([
                'status' => $status,
                'is_disable' => $status === 1 ? 0 : 1,
            ]);
            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            self::setError($e->getMessage());
            return false;
        }
    }

    private static function buildListQuery(array $params)
    {
        $query = Jobs::where([]);
        if (!empty($params['code'])) {
            $query->where('code', trim((string)$params['code']));
        }
        if (!empty($params['name'])) {
            $query->whereLike('name', '%' . trim((string)$params['name']) . '%');
        }
        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', (int)$params['status']);
        }
        return $query->order(['sort' => 'desc', 'id' => 'desc']);
    }

    private static function assertUnique(string $name, string $code, int $exceptId = 0): void
    {
        $nameQuery = Jobs::where('name', trim($name));
        $codeQuery = Jobs::where('code', trim($code));
        if ($exceptId > 0) {
            $nameQuery->where('id', '<>', $exceptId);
            $codeQuery->where('id', '<>', $exceptId);
        }
        if ($nameQuery->count() > 0) {
            throw new \RuntimeException('岗位名称已存在');
        }
        if ($codeQuery->count() > 0) {
            throw new \RuntimeException('岗位编码已存在');
        }
    }

    private static function exportInfo(int $count, int $pageSize): array
    {
        $sumPage = max(1, (int)ceil($count / $pageSize));
        return [
            'count' => $count,
            'page_size' => $pageSize,
            'sum_page' => $sumPage,
            'max_page' => (int)floor(self::EXPORT_MAX_ROWS / $pageSize),
            'all_max_size' => self::EXPORT_MAX_ROWS,
            'page_start' => 1,
            'page_end' => min($sumPage, 200),
            'file_name' => self::EXPORT_DEFAULT_NAME,
        ];
    }

    private static function export(array $params, int $count, int $pageSize): array
    {
        if ($count === 0) {
            throw new \RuntimeException('没有数据，无法导出');
        }

        $pageType = (int)($params['page_type'] ?? 0);
        if ($pageType === 1) {
            $pageStart = max(1, (int)($params['page_start'] ?? 1));
            $pageEnd = max($pageStart, (int)($params['page_end'] ?? $pageStart));
            $offset = ($pageStart - 1) * $pageSize;
            $limit = ($pageEnd - $pageStart + 1) * $pageSize;
            if ($limit > self::EXPORT_MAX_ROWS) {
                throw new \RuntimeException('已超出系统导出限制，当前最多导出25000条记录');
            }
            if ($offset >= $count) {
                throw new \RuntimeException('所选分页范围没有数据，无法导出');
            }
        } else {
            $offset = 0;
            $limit = min($count, self::EXPORT_MAX_ROWS);
        }

        $rows = self::buildListQuery($params)
            ->append(['status_desc'])
            ->limit($offset, $limit)
            ->select()
            ->toArray();
        $rows = self::formatRows($rows);
        $uri = self::createXlsx($rows, (string)($params['file_name'] ?? self::EXPORT_DEFAULT_NAME));

        return [
            'url' => FileService::getFileUrl($uri),
            'file_name' => basename($uri),
        ];
    }

    private static function createXlsx(array $rows, string $requestedName): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new \RuntimeException('服务器未安装 ZipArchive 扩展，无法导出 XLSX');
        }

        $name = trim($requestedName) !== '' ? trim($requestedName) : self::EXPORT_DEFAULT_NAME;
        $name = preg_replace('/[\\\\\/:*?"<>|]+/u', '_', $name) ?: self::EXPORT_DEFAULT_NAME;
        $name = preg_replace('/\.xlsx$/i', '', $name) ?: self::EXPORT_DEFAULT_NAME;
        $fileName = $name . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.xlsx';
        $directory = public_path('storage/exports');
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('导出目录创建失败');
        }
        $path = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;

        $sheetRows = [['岗位编码', '岗位名称', '备注', '状态', '添加时间']];
        foreach ($rows as $row) {
            $sheetRows[] = [
                $row['code'],
                $row['name'],
                $row['remark'],
                $row['status_desc'],
                $row['create_time'],
            ];
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('导出文件创建失败');
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="岗位列表" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', self::worksheetXml($sheetRows));
        $zip->close();

        return 'storage/exports/' . $fileName;
    }

    private static function worksheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($rows as $rowIndex => $row) {
            $number = $rowIndex + 1;
            $xml .= '<row r="' . $number . '">';
            foreach (array_values($row) as $columnIndex => $value) {
                $cell = self::columnName($columnIndex + 1) . $number;
                $text = preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', (string)$value) ?? '';
                $text = htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
                $xml .= '<c r="' . $cell . '" t="inlineStr"><is><t xml:space="preserve">'
                    . $text . '</t></is></c>';
            }
            $xml .= '</row>';
        }
        return $xml . '</sheetData></worksheet>';
    }

    private static function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)) . $name;
            $number = intdiv($number, 26);
        }
        return $name;
    }

    private static function formatRows(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['id'] = (int)$row['id'];
            $row['sort'] = (int)$row['sort'];
            $row['status'] = (int)$row['status'];
            $row['is_disable'] = $row['status'] === 1 ? 0 : 1;
            $row['status_desc'] = $row['status'] === 1 ? '正常' : '停用';
            $row['create_time'] = self::formatTime($row['create_time'] ?? 0);
            $row['update_time'] = self::formatTime($row['update_time'] ?? 0);
        }
        unset($row);
        return $rows;
    }

    private static function formatTime($value): string
    {
        if (empty($value)) {
            return '';
        }
        if (!is_numeric($value)) {
            return (string)$value;
        }
        return date('Y-m-d H:i:s', (int)$value);
    }
}
