<?php
declare(strict_types=1);

namespace app\adminapi\validate\dept;

use app\common\model\dept\Jobs;
use think\Validate;

class JobsValidate extends Validate
{
    protected $rule = [
        'id'     => 'require|integer|gt:0|checkJobs',
        'name'   => 'require|length:1,50|checkUniqueName',
        'code'   => 'require|max:64|checkUniqueCode',
        'sort'   => 'integer|egt:0',
        'remark' => 'max:200',
        'status' => 'require|in:0,1',
    ];

    protected $message = [
        'id.require'     => '参数缺失',
        'name.require'   => '请填写岗位名称',
        'name.length'    => '岗位名称长度须在1-50位字符',
        'code.require'   => '请填写岗位编码',
        'code.max'       => '岗位编码最多64个字符',
        'sort.integer'   => '排序值不正确',
        'sort.egt'       => '排序值不正确',
        'remark.max'     => '岗位备注最多200个字符',
        'status.require' => '请选择岗位状态',
        'status.in'      => '岗位状态参数错误',
    ];

    protected $scene = [
        'add'    => ['name', 'code', 'sort', 'remark', 'status'],
        'edit'   => ['id', 'name', 'code', 'sort', 'remark', 'status'],
        'delete' => ['id'],
        'detail' => ['id'],
        'status' => ['id', 'status'],
    ];

    protected function checkJobs($value): bool|string
    {
        return Jobs::findOrEmpty((int)$value)->isEmpty() ? '岗位不存在' : true;
    }

    protected function checkUniqueName($value, $rule, array $data = []): bool|string
    {
        $query = Jobs::where('name', trim((string)$value));
        $id = (int)($data['id'] ?? 0);
        if ($id > 0) {
            $query->where('id', '<>', $id);
        }
        return $query->findOrEmpty()->isEmpty() ? true : '岗位名称已存在';
    }

    protected function checkUniqueCode($value, $rule, array $data = []): bool|string
    {
        $query = Jobs::where('code', trim((string)$value));
        $id = (int)($data['id'] ?? 0);
        if ($id > 0) {
            $query->where('id', '<>', $id);
        }
        return $query->findOrEmpty()->isEmpty() ? true : '岗位编码已存在';
    }
}
