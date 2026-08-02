<?php
declare(strict_types=1);

namespace app\adminapi\validate\auth;

use app\common\model\auth\Admin;
use app\common\model\auth\SystemRole;
use app\common\model\dept\Dept;
use app\common\model\dept\Jobs;
use think\Validate;

class AdminValidate extends Validate
{
    protected $rule = [
        'id' => 'require|integer|gt:0|checkAdmin',
        'account' => 'require|length:1,32|checkAccountUnique',
        'name' => 'require|length:1,16|checkNameUnique',
        'avatar' => 'max:255',
        'password' => 'length:6,32',
        'password_confirm' => 'requireWith:password|checkPasswordConfirm',
        'role_id' => 'array|checkRoleIds',
        'dept_id' => 'array|checkDeptIds',
        'jobs_id' => 'array|checkJobsIds',
        'disable' => 'require|in:0,1|checkRootDisable',
        'multipoint_login' => 'require|in:0,1',
        'page_no' => 'integer|gt:0',
        'page_size' => 'integer|between:1,25000',
        'page_type' => 'in:0,1',
        'page_start' => 'integer|gt:0',
        'page_end' => 'integer|gt:0|checkPageEnd',
        'field' => 'in:id,create_time',
        'order_by' => 'in:asc,desc',
        'export' => 'in:1,2',
        'file_name' => 'max:100',
    ];

    protected $message = [
        'id.require' => '管理员id不能为空',
        'id.integer' => '管理员id格式错误',
        'id.gt' => '管理员id格式错误',
        'account.require' => '账号不能为空',
        'account.length' => '账号长度须在1-32位字符',
        'name.require' => '名称不能为空',
        'name.length' => '名称须在1-16位字符',
        'avatar.max' => '头像地址不能超过255个字符',
        'password.require' => '密码不能为空',
        'password.length' => '密码长度须在6-32位字符',
        'password_confirm.requireWith' => '确认密码不能为空',
        'role_id.require' => '请选择角色',
        'role_id.array' => '角色格式错误',
        'dept_id.array' => '部门格式错误',
        'jobs_id.array' => '岗位格式错误',
        'disable.require' => '请选择状态',
        'disable.in' => '状态值错误',
        'multipoint_login.require' => '请选择是否支持多处登录',
        'multipoint_login.in' => '多处登录状态值错误',
        'page_no.integer' => '页码必须为整数',
        'page_no.gt' => '页码必须大于0',
        'page_size.integer' => '每页数量必须为整数',
        'page_size.between' => '每页数量须在1-25000之间',
        'page_type.in' => '导出范围类型错误',
        'page_start.integer' => '导出起始页必须为整数',
        'page_start.gt' => '导出起始页必须大于0',
        'page_end.integer' => '导出结束页必须为整数',
        'page_end.gt' => '导出结束页必须大于0',
        'field.in' => '排序字段错误',
        'order_by.in' => '排序方式错误',
        'export.in' => '导出类型错误',
        'file_name.max' => '导出文件名不能超过100个字符',
    ];

    protected $scene = [
        'lists' => [
            'account', 'name', 'role_id', 'page_no', 'page_size', 'page_type',
            'page_start', 'page_end', 'field', 'order_by', 'export', 'file_name',
        ],
        'detail' => ['id'],
        'delete' => ['id'],
        'status' => ['id', 'disable'],
        'edit' => [
            'id', 'account', 'name', 'avatar', 'password', 'password_confirm',
            'role_id', 'dept_id', 'jobs_id', 'disable', 'multipoint_login',
        ],
    ];

    public function sceneLists(): self
    {
        return $this->only($this->scene['lists'])
            ->remove('account', 'require|checkAccountUnique')
            ->remove('name', 'require|checkNameUnique')
            ->remove('role_id', 'array|checkRoleIds')
            ->append('role_id', 'integer|gt:0');
    }

    public function sceneAdd(): self
    {
        return $this->only([
            'account', 'name', 'avatar', 'password', 'password_confirm',
            'role_id', 'dept_id', 'jobs_id', 'disable', 'multipoint_login',
        ])->append('password', 'require')
            ->append('role_id', 'require');
    }

    public function checkAdmin($value): bool|string
    {
        return Admin::findOrEmpty((int)$value)->isEmpty() ? '管理员不存在' : true;
    }

    public function checkAccountUnique($value, $rule, array $data): bool|string
    {
        $query = Admin::where('username', (string)$value);
        if (!empty($data['id'])) {
            $query->where('id', '<>', (int)$data['id']);
        }
        return $query->count() > 0 ? '账号已存在' : true;
    }

    public function checkNameUnique($value, $rule, array $data): bool|string
    {
        $query = Admin::where('nickname', (string)$value);
        if (!empty($data['id'])) {
            $query->where('id', '<>', (int)$data['id']);
        }
        return $query->count() > 0 ? '名称已存在' : true;
    }

    public function checkPasswordConfirm($value, $rule, array $data): bool|string
    {
        $password = (string)($data['password'] ?? '');
        if ($password === '') {
            return true;
        }
        if (!array_key_exists('password_confirm', $data) || (string)$value === '') {
            return '确认密码不能为空';
        }
        return hash_equals($password, (string)$value) ? true : '两次输入的密码不一致';
    }

    public function checkRoleIds($value, $rule, array $data): bool|string
    {
        $ids = $this->normalizeIds($value);
        $admin = !empty($data['id']) ? Admin::findOrEmpty((int)$data['id']) : null;
        if ($ids === [] && !($admin && !$admin->isEmpty() && (int)$admin->root === 1)) {
            return '请选择角色';
        }
        if ($ids !== [] && SystemRole::whereIn('id', $ids)->count() !== count($ids)) {
            return '选择的角色不存在';
        }
        return true;
    }

    public function checkDeptIds($value): bool|string
    {
        $ids = $this->normalizeIds($value);
        if ($ids !== [] && Dept::whereIn('id', $ids)->count() !== count($ids)) {
            return '选择的部门不存在';
        }
        return true;
    }

    public function checkJobsIds($value): bool|string
    {
        $ids = $this->normalizeIds($value);
        if ($ids !== [] && Jobs::whereIn('id', $ids)->count() !== count($ids)) {
            return '选择的岗位不存在';
        }
        return true;
    }

    public function checkRootDisable($value, $rule, array $data): bool|string
    {
        if (empty($data['id']) || (int)$value === 0) {
            return true;
        }
        $admin = Admin::findOrEmpty((int)$data['id']);
        return !$admin->isEmpty() && (int)$admin->root === 1
            ? '超级管理员不允许被禁用'
            : true;
    }

    public function checkPageEnd($value, $rule, array $data): bool|string
    {
        $start = (int)($data['page_start'] ?? 1);
        return (int)$value < $start ? '导出范围设置不正确，请重新选择' : true;
    }

    /** @return int[] */
    private function normalizeIds($value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $ids = array_values(array_unique(array_map('intval', $value)));
        return array_values(array_filter($ids, static fn(int $id): bool => $id > 0));
    }
}
