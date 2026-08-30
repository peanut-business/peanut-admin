<?php
declare(strict_types=1);

namespace app\Modules\Official\File\Http\Controller;

use app\adminapi\controller\BaseAdminController;
use app\Modules\Official\File\Contracts\FileAdministration;
use app\Modules\Official\File\Validation\FileCateValidate;
use think\App;

class FileController extends BaseAdminController
{
    public function __construct(App $app, private readonly FileAdministration $files)
    {
        parent::__construct($app);
    }

    // ---- 文件 ----
    public function lists()
    {
        return $this->data($this->files->lists($this->request->get()));
    }

    public function move()
    {
        $ids = (array)$this->request->post('ids', []);
        $this->files->move(
            array_map('intval', $ids),
            $this->integerValue($this->request->post('cid', 0), '目标分类无效'),
        );
        return $this->success('操作成功');
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
        $this->files->rename(
            $this->integerValue($this->request->post('id'), '素材 ID 无效'),
            $name,
        );
        return $this->success('操作成功');
    }

    public function delete()
    {
        $ids = (array)$this->request->post('ids', []);
        $result = $this->files->delete(array_map('intval', $ids));
        return $this->success('操作成功', $result);
    }

    // ---- 分类 ----
    public function listCate()
    {
        return $this->data($this->files->categoryLists(
            $this->integerValue($this->request->get('type', 10), '文件类型无效'),
        ));
    }

    public function addCate()
    {
        $this->validate($this->request->post(), FileCateValidate::class . '.add');
        $this->files->addCategory($this->request->post());
        return $this->success('操作成功');
    }

    public function editCate()
    {
        $this->validate($this->request->post(), FileCateValidate::class . '.edit');
        $this->files->editCategory($this->request->post());
        return $this->success('操作成功');
    }

    public function delCate()
    {
        $result = $this->files->deleteCategory(
            $this->integerValue($this->request->post('id'), '分类 ID 无效'),
        );
        return $this->success('操作成功', $result);
    }

    private function integerValue(mixed $value, string $message): int
    {
        if (!is_int($value) && !(is_string($value) && preg_match('/^-?\d+$/D', $value) === 1)) {
            throw new \InvalidArgumentException($message);
        }
        return (int)$value;
    }
}
