# LikeAdmin 1.9.4 管理员业务契约

> 任务：O01/O02  
> 状态：已验收  
> 验收日期：2026-07-28

## 1. 产品能力

管理员模块必须提供：

1. 按账号、名称和角色筛选，支持查询与重置；
2. 服务端分页，支持 `page_no`、`page_size`、`id/create_time` 排序；
3. 两阶段 XLSX 导出；
4. 新增、详情、编辑、禁用/启用和软删除；
5. 维护管理员与角色、部门、岗位的多对多关系；
6. 角色变化、禁用和删除时强制失效管理员会话；
7. 支持单点/多处登录策略字段。

前端框架与控件视觉差异可接受，以上业务入口、字段、校验和状态行为不可缺失。

## 2. 列表与筛选契约

请求字段：

| 字段 | 类型 | 语义 |
|---|---|---|
| `account` | string | 账号模糊匹配 |
| `name` | string | 名称模糊匹配 |
| `role_id` | int | 按管理员角色关系筛选 |
| `page_no` | int | 页码，从 1 开始 |
| `page_size` | int | 每页数量，最大 25000 |
| `field` | string | `id` 或 `create_time` |
| `order_by` | string | `asc` 或 `desc` |

正常列表响应沿用 Peanut 分页封装：

```json
{
  "lists": [],
  "count": 0,
  "pageNo": 1,
  "pageSize": 15
}
```

每行必须包含：`id/account/name/avatar/root/disable/disable_desc/multipoint_login/login_time/login_ip/create_time/update_time/role_id/dept_id/jobs_id/role_name/dept_name/jobs_name`。同时输出 `username/nickname/role_ids/roles` 兼容 Peanut 既有调用方。

## 3. 导出契约

`export=1` 返回导出信息：

| 字段 | 语义 |
|---|---|
| `count` | 筛选后的总记录数 |
| `page_size` | 每页数量 |
| `sum_page` | 总页数，最小为 1 |
| `max_page` | 单次最多页数 |
| `all_max_size` | 单次最多记录数，当前 25000 |
| `page_start/page_end` | 默认分页范围 |
| `file_name` | 默认文件名“管理员列表” |

`export=2` 接受 `page_type/page_start/page_end/file_name`，生成 XLSX 并返回 `url/file_name`。导出列顺序为账号、名称、角色、部门、创建时间、最近登录时间、最近登录 IP、状态。

## 4. 表单与校验

| 字段 | 新增 | 编辑 | 规则 |
|---|---|---|---|
| `account` | 必填 | 必填 | 1-32 字符，活跃管理员内唯一 |
| `name` | 必填 | 必填 | 1-16 字符，活跃管理员内唯一 |
| `avatar` | 可选 | 可选 | 最大 255 字符，存相对 URI |
| `dept_id` | 可选 | 可选 | 数组，部门必须存在 |
| `jobs_id` | 可选 | 可选 | 数组，岗位必须存在 |
| `role_id` | 必填 | 非 root 必填 | 数组，角色必须存在 |
| `password` | 必填 | 可选 | 6-32 字符；编辑留空不修改 |
| `password_confirm` | 随密码必填 | 随密码必填 | 必须与密码一致 |
| `disable` | 必填 | 必填 | `0` 正常、`1` 禁用 |
| `multipoint_login` | 必填 | 必填 | `1` 允许、`0` 不允许 |

Peanut 继续使用现有随机盐双 MD5 密码方向，不机械复制参考版全局盐实现。

## 5. 事务与状态规则

- 新增：管理员主表与三类关系在同一事务中写入。
- 编辑：锁定管理员行，更新基础字段并整体替换三类关系。
- 角色集合变化：强制过期该管理员所有服务端会话。
- 禁用状态变化：强制过期该管理员所有服务端会话。
- 删除：禁止删除 root；软删除管理员，清空角色/部门/岗位关系并强制过期会话。
- root 不要求角色，且不允许禁用或删除。
- Peanut 额外阻止当前登录管理员删除或禁用自己，避免自锁。

`admin/status` 是 Peanut 的轻量状态接口，但权限按 `admin/edit` 计算，不得利用“未登记 URI 放行”绕过管理员编辑权限。

## 6. 数据表映射

| LikeAdmin | Peanut | 说明 |
|---|---|---|
| `la_admin.account` | `pa_admin.username` | 对外统一输出 `account` |
| `la_admin.name` | `pa_admin.nickname` | 对外统一输出 `name` |
| `la_admin_role` | `pa_admin_role` | 角色关系 |
| `la_admin_dept` | `pa_admin_dept` | 部门关系 |
| `la_admin_jobs` | `pa_admin_jobs` | 岗位关系 |
| `la_admin_session` | `pa_admin_session` | 服务端会话 |

管理员账号只在未删除数据中唯一。`pa_admin.username` 使用普通索引，避免软删除行阻止账号复用。

## 7. 真实验收证据

- LikeAdmin：真实进入管理员页，完成账号查询，核对名称/角色筛选、分页控件和全部列表字段；打开两阶段导出设置并实际下载 XLSX；打开新增表单核对全部字段和默认值。
- Peanut：真实新增 `parity_admin_0728`，绑定普通管理员角色和行政部；详情回显角色、部门，编辑加入临时岗位、修改名称并关闭多处登录。
- 会话：临时管理员登录与 `info` 均返回 `20000`；页面禁用后同 token 返回 `40100 / 登录超时，请重新登录`。
- 导出：筛选普通管理员后第一阶段显示 1 条、1 页、每页 15 条、最多 25000 条；第二阶段实际下载 XLSX，OpenXML 压缩结构检查通过。
- 清理：管理员 ID 9 已软删除；角色、部门、岗位关系计数均为 0；1 条会话已过期；临时岗位已软删除。

## 8. 已接受的安全偏差

以下行为不复制参考缺陷：

1. 参考版角色筛选在无管理员时会遗漏 `WHERE IN`，错误返回全部；Peanut 返回空集。
2. 参考版允许非 root 管理员删除自己；Peanut 禁止删除或禁用当前登录管理员。

这两项不会减少产品能力，且避免数据泄漏和管理员自锁。
