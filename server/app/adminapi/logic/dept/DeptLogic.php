<?php
declare(strict_types=1);

namespace app\adminapi\logic\dept;

use app\common\logic\BaseLogic;
use app\common\model\auth\AdminDept;
use app\common\model\dept\Dept;
use app\common\service\org\OrgTenantRepository;
use PeanutAdmin\Kernel\Auth\TenantContext;
use think\facade\Db;

class DeptLogic extends BaseLogic
{
    public static function validationRules(string $scene): array
    {
        $rules = [
            'id' => 'require|integer|gt:0', 'name' => 'require|length:1,30',
            'pid' => 'require|integer|egt:0', 'leader' => 'max:50', 'mobile' => 'max:20',
            'sort' => 'integer|egt:0', 'status' => 'require|in:0,1',
        ];
        if ($scene === 'add') {
            unset($rules['id']);
        }
        return $rules;
    }

    /** 部门树（支持名称和状态筛选，不分页） */
    public static function lists(TenantContext $context, array $params = []): array
    {
        $where = [];
        if (!empty($params['name'])) {
            $where[] = ['name', 'like', '%' . trim((string)$params['name']) . '%'];
        }
        if (isset($params['status']) && $params['status'] !== '') {
            $where[] = ['status', '=', (int)$params['status']];
        }

        $data = self::departments($context)->where($where)
            ->append(['status_desc'])
            ->order(['sort' => 'desc', 'id' => 'desc'])
            ->select()
            ->toArray();

        return self::buildTree($data);
    }

    /** 正常部门树 */
    public static function all(TenantContext $context): array
    {
        $data = self::departments($context)->where('status', 1)
            ->order(['sort' => 'desc', 'id' => 'desc'])
            ->select()
            ->toArray();

        return self::buildTree($data);
    }

    /** 正常部门扁平列表（负责人部门选择器） */
    public static function leaderDept(TenantContext $context): array
    {
        return self::departments($context)->field('id,name')
            ->where('status', 1)
            ->order(['sort' => 'desc', 'id' => 'desc'])
            ->select()
            ->toArray();
    }

    public static function detail(TenantContext $context, int $id): array
    {
        return self::departments($context)->where('id', $id)->findOrEmpty()->toArray();
    }

    public static function add(TenantContext $context, array $params): bool
    {
        Db::startTrans();
        try {
            $status = (int)$params['status'];
            self::assertUniqueName($context, (string)$params['name']);
            self::assertParent($context, (int)$params['pid']);
            OrgTenantRepository::create($context, Dept::class, [
                'pid'        => (int)$params['pid'],
                'name'       => (string)$params['name'],
                'leader'     => (string)($params['leader'] ?? ''),
                'mobile'     => (string)($params['mobile'] ?? ''),
                'sort'       => (int)($params['sort'] ?? 0),
                'status'     => $status,
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

    public static function edit(TenantContext $context, array $params): bool
    {
        Db::startTrans();
        try {
            $dept = self::departments($context)->where('id', (int)$params['id'])->lock(true)->findOrEmpty();
            if ($dept->isEmpty()) {
                throw new \RuntimeException('部门不存在');
            }
            $pid = (int)$dept->pid === 0 ? 0 : (int)$params['pid'];
            self::assertParent($context, $pid, (int)$dept->id);
            self::assertUniqueName($context, (string)$params['name'], (int)$dept->id);
            $status = (int)$params['status'];
            $dept->save([
                'pid'        => $pid,
                'name'       => (string)$params['name'],
                'leader'     => (string)($params['leader'] ?? ''),
                'mobile'     => (string)($params['mobile'] ?? ''),
                'sort'       => (int)($params['sort'] ?? 0),
                'status'     => $status,
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

    public static function delete(TenantContext $context, int $id): bool
    {
        Db::startTrans();
        try {
            $dept = self::departments($context)->where('id', $id)->lock(true)->findOrEmpty();
            if ($dept->isEmpty()) {
                throw new \RuntimeException('部门不存在');
            }
            if (self::departments($context)->where('pid', $id)->count() > 0) {
                throw new \RuntimeException('已关联下级部门,暂不可删除');
            }
            if (OrgTenantRepository::query($context, AdminDept::class)->where('dept_id', $id)->count() > 0) {
                throw new \RuntimeException('已关联管理员，暂不可删除');
            }
            if ((int)$dept->pid === 0) {
                throw new \RuntimeException('顶级部门不可删除');
            }

            $dept->delete();
            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            self::setError($e->getMessage());
            return false;
        }
    }

    public static function updateStatus(TenantContext $context, int $id, int $status): bool
    {
        Db::startTrans();
        try {
            $dept = self::departments($context)->where('id', $id)->lock(true)->findOrEmpty();
            if ($dept->isEmpty()) {
                throw new \RuntimeException('部门不存在');
            }
            $dept->save([
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

    private static function buildTree(array $data): array
    {
        if (empty($data)) {
            return [];
        }
        $pid = min(array_column($data, 'pid'));
        return self::getTree($data, (int)$pid);
    }

    private static function departments(TenantContext $context)
    {
        return OrgTenantRepository::query($context, Dept::class);
    }

    private static function assertUniqueName(TenantContext $context, string $name, int $exceptId = 0): void
    {
        $query = self::departments($context)->where('name', trim($name));
        if ($exceptId > 0) {
            $query->where('id', '<>', $exceptId);
        }
        if ($query->count() > 0) {
            throw new \RuntimeException('部门名称已存在');
        }
    }

    private static function assertParent(TenantContext $context, int $parentId, int $currentId = 0): void
    {
        if ($parentId === 0) {
            return;
        }
        $visited = [];
        while ($parentId > 0) {
            if ($parentId === $currentId || isset($visited[$parentId])) {
                throw new \RuntimeException('上级部门不可是当前部门或其下级部门');
            }
            $visited[$parentId] = true;
            $parent = self::departments($context)->where('id', $parentId)->findOrEmpty();
            if ($parent->isEmpty()) {
                throw new \RuntimeException('部门不存在');
            }
            if ((int)$parent->status !== 1) {
                throw new \RuntimeException('上级部门已停用');
            }
            $parentId = (int)$parent->pid;
        }
    }

    private static function getTree(array $data, int $pid, int $level = 0): array
    {
        $tree = [];
        foreach ($data as $item) {
            if ((int)$item['pid'] !== $pid) {
                continue;
            }
            $item['level'] = $level;
            $item['children'] = self::getTree($data, (int)$item['id'], $level + 1);
            $tree[] = $item;
        }
        return $tree;
    }
}
