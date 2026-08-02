# C01 LikeAdmin 1.9.4 文章栏目契约

## 定位结果

- 动态路由装配：`admin/src/router/index.ts`。路由由后端菜单树按父子层级拼接，组件通过 `import.meta.glob('/src/views/**/*.vue')` 动态载入。
- 菜单契约：应用管理 → 文章资讯 → 文章栏目；菜单路径 `column`，组件 `article/column/index`，运行时完整地址为 `/admin/app/article/column`。
- 列表页面：`admin/src/views/article/column/index.vue`。
- 新增/编辑弹窗：`admin/src/views/article/column/edit.vue`。
- API 封装：`admin/src/api/article.ts`。

## API 契约

| 操作 | 方法 | 路径 | 本页触发点 |
| --- | --- | --- | --- |
| 分页列表 | GET | `/article.articleCate/lists` | 页面加载、分页刷新 |
| 全部分类 | GET | `/article.articleCate/all` | API 已定义，本页未调用 |
| 新增 | POST | `/article.articleCate/add` | 新增弹窗校验通过后确认 |
| 编辑 | POST | `/article.articleCate/edit` | 编辑弹窗校验通过后确认 |
| 删除 | POST | `/article.articleCate/delete` | 二次确认后提交 `{id}` |
| 详情 | GET | `/article.articleCate/detail` | 打开编辑弹窗时提交 `{id}` |
| 状态 | POST | `/article.articleCate/updateStatus` | 状态开关提交 `{id,is_show}`；成功或失败均刷新列表 |

分页由 `usePaging` 注入 `page_no`、`page_size`。运行时第一页请求为 `page_no=1&page_size=15`。

## 页面字段与业务规则

- 顶部固定提示：`用于管理网站的分类，只可添加到一级`。
- 列表为普通平面表格，不是树表；列为 `栏目名称(name)`、`文章数(article_count)`、`状态(is_show)`、`排序(sort)`、`操作`。
- 状态开关值域为 `1/0`。
- 没有父级、层级、路径、展开/折叠或子节点交互；新增/编辑表单也没有父级选择。因此 C01 的参考契约是“仅一级栏目”，不应实现树形分类交互。
- 删除前展示 `确定要删除？` 二次确认。

## 新增/编辑表单

- `栏目名称(name)`：必填；占位符及空值提示均为 `请输入栏目名称`，触发时机为 `blur`，提交前也执行全表单校验。
- `排序(sort)`：数字输入，最小 `0`、最大 `9999`，默认 `0`；提示 `默认为0， 数值越大越排前`。
- `状态(is_show)`：开关，值域 `1/0`，新增默认 `1`。
- 新增标题 `新增栏目`，编辑标题 `编辑栏目`；共用同一表单，编辑时先 GET 详情并回填 `id/name/sort/is_show`。

## 按钮权限

- 新增：`article.articleCate/add`
- 状态：`article.articleCate/updateStatus`
- 编辑：`article.articleCate/edit`
- 删除：`article.articleCate/delete`
- 页面菜单/列表：`article.articleCate/lists`
- 编辑详情接口：`article.articleCate/detail`

参考源码、安装 SQL 和当前 LikeAdmin 数据库均只登记了 `lists/add/delete/detail/updateStatus`，没有登记 UI 使用的 `article.articleCate/edit`。root 管理员因权限旁路仍可看到“编辑”。这是 LikeAdmin 1.9.4 参考端本身的权限登记缺口，复刻验收时需单独标记，不能误认为不存在编辑功能。
