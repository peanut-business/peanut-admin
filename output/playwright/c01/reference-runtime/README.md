# C01 参考端运行时证据

LikeAdmin 1.9.4 的文章分类产品名称为“文章栏目”，实际路由是 `/admin/app/article/column`。CodeGraph 契约与真实浏览器观察一致：它是只能添加一级栏目的平面列表，不存在树形分类交互。

真实浏览器只读观察结果：列表共 3 条，新增弹窗的名称必填校验已触发，编辑弹窗通过 `detail?id=3` 回填“好物”；未提交表单，未点击删除或状态开关，网络记录中没有分类写请求。

证据文件：

- `codegraph-contract.md`：路由、API、字段、权限、交互与校验契约。
- `runtime-observation.json`：结构化运行时观察及临时账号清理结果。
- `network.log`：列表和详情的真实请求记录。
- `list.png`、`add-form.png`、`edit-form.png`：列表、新增和编辑界面截图。
- `*.snapshot.yml`：可访问性树快照；`add-validation.snapshot.yml` 包含空名称校验提示。

隔离观察账号及其会话、角色关系和操作日志已精确删除，残留计数均为 0；文章栏目数据未发生写入。
