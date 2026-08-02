<?php
declare(strict_types=1);

namespace app\adminapi\logic\member;

use app\common\enum\AccountLogEnum;
use app\common\enum\MemberChannelEnum;
use app\common\logic\AccountLogLogic;
use app\common\logic\BaseLogic;
use app\common\model\member\Member;
use app\common\model\member\MemberTag;
use app\common\model\member\MemberTagRelation;
use app\common\service\FileService;
use think\facade\Db;
use ZipArchive;

class MemberLogic extends BaseLogic
{
    private const EXPORT_MAX_ROWS = 25000;
    private const EXPORT_DEFAULT_NAME = '用户列表';

    /**
     * 用户分页列表；export=1 返回导出信息，export=2 生成 XLSX 并返回 URL。
     *
     * @return array|false
     */
    public static function lists(array $params): array|false
    {
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

    public static function detail(int $id): array
    {
        $member = Member::field([
            'id', 'sn', 'account', 'nickname', 'avatar', 'real_name',
            'sex', 'mobile', 'create_time', 'login_time', 'channel',
            'user_money', 'balance',
        ])->findOrEmpty($id);
        if ($member->isEmpty()) {
            return [];
        }

        $data = $member->toArray();
        $data['id'] = (int)$data['id'];
        $data['sex'] = (int)$member->getData('sex');
        $data['channel'] = MemberChannelEnum::getDesc((int)$member->getData('channel'));
        $data['create_time'] = self::formatTime($member->getData('create_time'));
        $data['login_time'] = self::formatTime($member->getData('login_time'));
        $data['user_money'] = (float)$member->getData('user_money');
        $data['balance'] = $data['user_money'];
        return $data;
    }

    private static function buildListQuery(array $params)
    {
        $query = Member::with(['tags']);
        if (!empty($params['keyword'])) {
            $keyword = trim((string)$params['keyword']);
            $query->where('sn|nickname|mobile|account', 'like', '%' . $keyword . '%');
        }
        if (!empty($params['channel'])) {
            $query->where('channel', (int)$params['channel']);
        }
        if (!empty($params['create_time_start'])) {
            $query->where('create_time', '>=', strtotime((string)$params['create_time_start']));
        }
        if (!empty($params['create_time_end'])) {
            $query->where('create_time', '<=', strtotime((string)$params['create_time_end']));
        }

        // Peanut 原有状态筛选作为兼容扩展保留，不替代参考筛选字段。
        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', (int)$params['status']);
        }
        return $query->order('id', 'desc');
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

        $sheetRows = [['用户编号', '用户昵称', '账号', '手机号码', '注册来源', '注册时间']];
        foreach ($rows as $row) {
            $sheetRows[] = [
                $row['sn'],
                $row['nickname'],
                $row['account'],
                $row['mobile'],
                $row['channel'],
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
            . '<sheets><sheet name="用户列表" sheetId="1" r:id="rId1"/></sheets></workbook>');
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
        $sexDesc = [0 => '未知', 1 => '男', 2 => '女'];
        foreach ($rows as &$row) {
            $sex = (int)($row['sex'] ?? 0);
            $channel = (int)($row['channel'] ?? 0);
            $row['id'] = (int)$row['id'];
            $row['sex_value'] = $sex;
            $row['sex'] = $sexDesc[$sex] ?? '未知';
            $row['channel_value'] = $channel;
            $row['channel'] = MemberChannelEnum::getDesc($channel);
            $row['status'] = (int)($row['status'] ?? 1);
            $row['is_disable'] = $row['status'] === 1 ? 0 : 1;
            $row['user_money'] = (float)($row['user_money'] ?? $row['balance'] ?? 0);
            $row['balance'] = $row['user_money'];
            $row['total_recharge_amount'] = (float)($row['total_recharge_amount'] ?? 0);
            $row['create_time'] = self::formatTime($row['create_time'] ?? 0);
            $row['update_time'] = self::formatTime($row['update_time'] ?? 0);
            $row['login_time'] = self::formatTime($row['login_time'] ?? 0);
            $row['tag_ids'] = array_map('intval', array_column($row['tags'] ?? [], 'id'));
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

    public static function add(array $params): bool
    {
        Db::startTrans();
        try {
            $member = Member::create([
                'sn'       => Member::generateSn(),
                'nickname' => $params['nickname'],
                'avatar'   => $params['avatar']   ?? '',
                'mobile'   => $params['mobile']   ?? '',
                'email'    => $params['email']    ?? '',
                'sex'      => (int)($params['sex'] ?? 0),
                'birthday' => $params['birthday']  ?? null,
                'status'   => (int)($params['status'] ?? 1),
            ]);
            if (!empty($params['tag_ids'])) {
                self::syncTags($member->id, $params['tag_ids']);
            }
            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function editProfile(array $params): bool
    {
        Db::startTrans();
        try {
            $data = ['id' => $params['id']];
            foreach (['nickname', 'avatar', 'mobile', 'email', 'birthday'] as $f) {
                if (isset($params[$f])) $data[$f] = $params[$f];
            }
            foreach (['sex', 'status'] as $f) {
                if (isset($params[$f])) $data[$f] = (int)$params[$f];
            }
            Member::update($data);
            if (array_key_exists('tag_ids', $params)) {
                self::syncTags((int)$params['id'], (array)($params['tag_ids'] ?? []));
            }
            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            self::setError($e->getMessage());
            return false;
        }
    }

    /** LikeAdmin 后台用户详情的单字段更新语义。 */
    public static function setUserInfo(array $params): bool
    {
        try {
            Member::update([
                'id' => (int)$params['id'],
                (string)$params['field'] => $params['value'],
            ]);
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function updateStatus(int $id, int $status): bool
    {
        try {
            Member::update(['id' => $id, 'status' => $status]);
            return true;
        } catch (\Throwable $e) {
            self::setError($e->getMessage());
            return false;
        }
    }

    /** 调整用户余额并写入分类账户流水。 */
    public static function adjustUserMoney(array $params, int $adminId): bool
    {
        Db::startTrans();
        try {
            /** @var Member $member */
            $member = Member::lock(true)->findOrEmpty((int)$params['user_id']);
            if ($member->isEmpty()) {
                throw new \RuntimeException('用户不存在');
            }

            $num = abs(round((float)$params['num'], 2));
            $action = (int)$params['action'];
            $current = (float)$member->user_money;
            $after = round($current + ($action === AccountLogEnum::INC ? $num : -$num), 2);
            if ($after < 0) {
                throw new \RuntimeException('用户可用余额仅剩' . $current);
            }

            Member::update([
                'id' => $member->id,
                'user_money' => $after,
                // Peanut 原有 balance 字段作为兼容镜像同步更新。
                'balance' => $after,
            ]);

            $changeType = $action === AccountLogEnum::INC
                ? AccountLogEnum::USER_MONEY_INC_ADMIN
                : AccountLogEnum::USER_MONEY_DEC_ADMIN;
            if (AccountLogLogic::add(
                (int)$member->id,
                $changeType,
                $action,
                $num,
                '',
                (string)($params['remark'] ?? ''),
                [],
                $adminId
            ) === false) {
                throw new \RuntimeException('账户流水记录失败');
            }
            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            self::setError($e->getMessage());
            return false;
        }
    }

    /** Peanut 旧版 signed amount API 的兼容入口。 */
    public static function adjustBalance(int $id, float $amount, string $remark, int $adminId): bool
    {
        if ($amount == 0.0) {
            self::setError('调整金额不能为 0');
            return false;
        }

        return self::adjustUserMoney([
            'user_id' => $id,
            'action' => $amount > 0 ? AccountLogEnum::INC : AccountLogEnum::DEC,
            'num' => abs($amount),
            'remark' => $remark,
        ], $adminId);
    }

    /** 全量替换标签关联 */
    private static function syncTags(int $memberId, array $tagIds): void
    {
        MemberTagRelation::where('member_id', $memberId)->delete();
        if (!empty($tagIds)) {
            $rows = array_map(fn($tid) => ['member_id' => $memberId, 'tag_id' => (int)$tid], $tagIds);
            (new MemberTagRelation)->insertAll($rows);
        }
    }
}
