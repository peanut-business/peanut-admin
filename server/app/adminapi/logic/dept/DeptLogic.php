<?php
declare(strict_types=1);

namespace app\adminapi\logic\dept;

use app\common\logic\BaseLogic;
use app\common\model\auth\AdminDept;
use app\common\model\dept\Dept;
use think\facade\Db;

class DeptLogic extends BaseLogic
{
    /** 部门树（支持名称和状态筛选，不分页） */
    public static function lists(array $params = []): array
    {
        $where = [];
        if (!empty($params['name'])) {
            $where[] = ['name', 'like', '%' . trim((string)$params['name']) . '%'];
        }
        if (isset($params['status']) && $params['status'] !== '') {
            $where[] = ['status', '=', (int)$params['status']];
        }

        $data = Dept::where($where)
            ->append(['status_desc'])
            ->order(['sort' => 'desc', 'id' => 'desc'])
            ->select()
            ->toArray();

        return self::buildTree($data);
    }

    /** 正常部门树 */
    public static function all(): array
    {
        $data = Dept::where('status', 1)
            ->order(['sort' => 'desc', 'id' => 'desc'])
            ->select()
            ->toArray();

        return self::buildTree($data);
    }

    /** 正常部门扁平列表（负责人部门选择器） */
    public static function leaderDept(): array
    {
        return Dept::field('id,name')
            ->where('status', 1)
            ->order(['sort' => 'desc', 'id' => 'desc'])
            ->select()
            ->toArray();
    }

    public static function detail(int $id): array
    {
        return Dept::findOrEmpty($id)->toArray();
    }

    public static function add(array $params): bool
    {
        Db::startTrans();
        try {
            $status = (int)$params['status'];
            Dept::create([
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

    public static function edit(array $params): bool
    {
        Db::startTrans();
        try {
            $dept = Dept::findOrEmpty((int)$params['id']);
            if ($dept->isEmpty()) {
                throw new \RuntimeException('部门不存在');
            }
            $pid = (int)$dept->pid === 0 ? 0 : (int)$params['pid'];
            $status = (int)$params['status'];
            Dept::update([
                'id'         => (int)$params['id'],
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

    public static function delete(int $id): bool
    {
        Db::startTrans();
        try {
            $dept = Dept::findOrEmpty($id);
            if ($dept->isEmpty()) {
                throw new \RuntimeException('部门不存在');
            }
            if (Dept::where('pid', $id)->count() > 0) {
                throw new \RuntimeException('已关联下级部门,暂不可删除');
            }
            if (AdminDept::where('dept_id', $id)->count() > 0) {
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

    public static function updateStatus(int $id, int $status): bool
    {
        Db::startTrans();
        try {
            $dept = Dept::findOrEmpty($id);
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
