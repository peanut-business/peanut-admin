<?php
declare(strict_types=1);

namespace app\adminapi\validate\dept;

use app\common\model\dept\Dept;
use think\Validate;

class DeptValidate extends Validate
{
    protected $rule = [
        'id'     => 'require|integer|gt:0|checkDept',
        'name'   => 'require|length:1,30|checkUniqueName',
        'pid'    => 'require|integer|egt:0',
        'leader' => 'max:50',
        'mobile' => 'max:20',
        'sort'   => 'integer|egt:0',
        'status' => 'require|in:0,1',
    ];

    protected $message = [
        'id.require'     => '参数缺失',
        'name.require'   => '请填写部门名称',
        'name.length'    => '部门名称长度须在1-30位字符',
        'pid.require'    => '请选择上级部门',
        'pid.integer'    => '上级部门参数错误',
        'leader.max'   => '负责人最多 50 个字符',
        'mobile.max'   => '联系电话最多 20 个字符',
        'sort.egt'       => '排序值不正确',
        'status.require' => '请选择部门状态',
        'status.in'      => '部门状态参数错误',
    ];

    protected $scene = [
        'add'    => ['name', 'pid' => 'require|integer|egt:0|checkActiveParent', 'leader', 'mobile', 'sort', 'status'],
        'edit'   => ['id', 'name', 'pid' => 'require|integer|egt:0|checkEditParent', 'leader', 'mobile', 'sort', 'status'],
        'delete' => ['id'],
        'detail' => ['id'],
        'status' => ['id', 'status'],
    ];

    protected function checkDept($value): bool|string
    {
        return Dept::findOrEmpty((int)$value)->isEmpty() ? '部门不存在' : true;
    }

    protected function checkUniqueName($value, $rule, array $data = []): bool|string
    {
        $query = Dept::where('name', trim((string)$value));
        $id = (int)($data['id'] ?? 0);
        if ($id > 0) {
            $query->where('id', '<>', $id);
        }
        return $query->findOrEmpty()->isEmpty() ? true : '部门名称已存在';
    }

    protected function checkActiveParent($value): bool|string
    {
        $parent = Dept::findOrEmpty((int)$value);
        if ($parent->isEmpty()) {
            return '部门不存在';
        }
        return (int)$parent->status === 1 ? true : '上级部门已停用';
    }

    protected function checkEditParent($value, $rule, array $data = []): bool|string
    {
        $id = (int)($data['id'] ?? 0);
        $dept = Dept::findOrEmpty($id);
        if ($dept->isEmpty()) {
            return '当前部门信息缺失';
        }

        if ((int)$dept->pid === 0) {
            return true;
        }

        $parentId = (int)$value;
        if ($parentId === $id) {
            return '上级部门不可是当前部门';
        }

        $selectedParent = Dept::findOrEmpty($parentId);
        if ($selectedParent->isEmpty()) {
            return '部门不存在';
        }
        if ((int)$selectedParent->status !== 1) {
            return '上级部门已停用';
        }

        $visited = [];
        while ($parentId > 0) {
            if ($parentId === $id) {
                return '上级部门不可是当前部门或其下级部门';
            }
            if (isset($visited[$parentId])) {
                return '部门层级关系异常';
            }
            $visited[$parentId] = true;
            $parent = Dept::findOrEmpty($parentId);
            if ($parent->isEmpty()) {
                return '部门不存在';
            }
            $parentId = (int)$parent->pid;
        }

        return true;
    }
}
