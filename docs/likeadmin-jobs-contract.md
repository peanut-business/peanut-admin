# LikeAdmin 1.9.4 岗位管理复刻契约

## 范围

本契约冻结 O05 岗位管理的业务行为。Peanut 前端继续使用 Arco Design，视觉差异不作为缺陷；接口行为、字段规则、权限、状态、导出和关联删除约束必须一致。

参考实现：

- `likeadmin/server/app/adminapi/lists/dept/JobsLists.php`
- `likeadmin/server/app/adminapi/controller/dept/JobsController.php`
- `likeadmin/server/app/adminapi/application/dept/JobsApplicationService.php`
- `likeadmin/server/app/adminapi/validate/dept/JobsValidate.php`
- `likeadmin/admin/src/views/organization/post/index.vue`
- `likeadmin/admin/src/views/organization/post/edit.vue`

## 数据与状态

| 字段 | 规则 |
| --- | --- |
| `name` | 必填，1～50 个字符，未删除记录内唯一 |
| `code` | 必填，未删除记录内唯一；筛选时精确匹配 |
| `sort` | 整数且不小于 0；前端限制 0～9999 |
| `remark` | 可空，最多 200 个字符 |
| `status` | `1` 正常、`0` 停用 |
| `status_desc` | `正常` / `停用` |

Peanut 既有 `is_disable` 暂时保留作内部兼容字段，对外以 `status` 为权威字段。写入时双写：`is_disable = 1 - status`。

## 列表、详情与选择器

- 列表筛选：`code` 精确匹配、`name` 模糊匹配、`status` 精确匹配。
- 分页参数：`page_no`、`page_size`；默认每页 15 条。
- 排序：`sort desc, id desc`。
- 列表展示：岗位编码、岗位名称、排序、备注、添加时间、状态和操作。
- `all` 选择器只返回 `status=1` 的未删除岗位。
- 详情和写操作必须校验岗位存在；不存在时返回明确业务错误。

## 新增、编辑、状态与删除

- 新增和编辑均执行字段规则及名称/编码唯一性校验。
- 状态切换对外接收 `status`；兼容旧请求的 `is_disable`。
- 轻量状态接口 `jobs/status` 复用 `jobs/edit` 权限，不额外制造与参考系统不同的按钮权限。
- 删除使用软删除。
- 岗位已被 `admin_jobs` 关联时拒绝删除，错误信息固定为：`已关联管理员，暂不可删除`。
- 解除管理员关联后允许删除，软删除记录的名称和编码允许再次使用。

## 权限

| 类型 | 权限字符 |
| --- | --- |
| 菜单/列表 | `jobs/lists` |
| 新增 | `jobs/add` |
| 编辑及状态 | `jobs/edit` |
| 删除 | `jobs/delete` |

## 两阶段导出

列表接口使用与 LikeAdmin 相同的两阶段协议：

1. `export=1` 返回记录数、分页信息、导出上限、默认页区间和默认文件名。
2. `export=2` 按当前筛选条件及选择的全部/分页范围生成 XLSX，并返回下载 URL 与文件名。

- 默认文件名：`岗位列表`
- 最大导出记录数：25,000
- 导出字段顺序：岗位编码、岗位名称、备注、状态、添加时间
- 对应业务字段：`code/name/remark/status_desc/create_time`

## 不复制的参考缺陷

- 不允许删除不存在的岗位后仍返回成功。
- 不允许绕过管理员岗位关联约束直接删除。
- 所有写操作失败必须回滚并返回真实业务错误。
