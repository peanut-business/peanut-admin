<?php
declare(strict_types=1);

namespace app\adminapi\controller\file;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\logic\file\FileCateLogic;
use app\adminapi\logic\file\FileLogic;
use app\adminapi\validate\file\FileCateValidate;
use app\common\service\file\FileTenantContext;

class FileController extends BaseAdminController
{
    // ---- 文件 ----
    public function lists()
    {
        try {
            $res = FileLogic::lists(FileTenantContext::member($this->request), $this->request->get());
            return $this->dataLists($res['lists'], $res['count'], $res['pageNo'], $res['pageSize']);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function move()
    {
        $ids = (array)$this->request->post('ids', []);
        try {
            FileLogic::move(
                FileTenantContext::member($this->request),
                array_map('intval', $ids),
                $this->integerValue($this->request->post('cid', 0), '目标分类无效')
            );
            return $this->success('操作成功');
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function rename()
    {
        $name = trim((string)$this->request->post('name', ''));
        if ($name === '') {
            return $this->fail('名称不能为空');
        }
        if (mb_strlen($name) > 20) {
            return $this->fail('名称最多 20 个字符');
        }
        try {
            FileLogic::rename(
                FileTenantContext::member($this->request),
                $this->integerValue($this->request->post('id'), '素材 ID 无效'),
                $name
            );
            return $this->success('操作成功');
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function delete()
    {
        $ids = (array)$this->request->post('ids', []);
        try {
            $result = FileLogic::delete(FileTenantContext::member($this->request), array_map('intval', $ids));
            return $this->success('操作成功', $result);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    // ---- 分类 ----
    public function listCate()
    {
        try {
            return $this->data(FileCateLogic::lists(
                FileTenantContext::member($this->request),
                $this->integerValue($this->request->get('type', 10), '文件类型无效')
            ));
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function addCate()
    {
        $this->validate($this->request->post(), FileCateValidate::class . '.add');
        $r = FileCateLogic::add(FileTenantContext::member($this->request), $this->request->post());
        return $r ? $this->success('操作成功') : $this->fail(FileCateLogic::getError());
    }

    public function editCate()
    {
        $this->validate($this->request->post(), FileCateValidate::class . '.edit');
        $r = FileCateLogic::edit(FileTenantContext::member($this->request), $this->request->post());
        return $r ? $this->success('操作成功') : $this->fail(FileCateLogic::getError());
    }

    public function delCate()
    {
        try {
            $result = FileCateLogic::delete(
                FileTenantContext::member($this->request),
                $this->integerValue($this->request->post('id'), '分类 ID 无效')
            );
            return $this->success('操作成功', $result);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    private function integerValue(mixed $value, string $message): int
    {
        if (!is_int($value) && !(is_string($value) && preg_match('/^-?\d+$/D', $value) === 1)) {
            throw new \InvalidArgumentException($message);
        }
        return (int)$value;
    }
}
