# LikeAdmin 1.9.4 文章分类契约

> 任务：C01 文章分类  
> 状态：已完成；LikeAdmin 1.9.4 与 Peanut 的 C01-01～C01-10 双系统业务验收全部通过  
> 契约来源：LikeAdmin 1.9.4 当前源码、安装 SQL、CodeGraph 调用链、真实浏览器/API 运行结果和数据库不变量  
> 边界：本文只冻结文章分类；文章 CRUD、发布状态和内容查询归入 C02

## 1. 结论与实现边界

LikeAdmin 的“文章栏目”是**扁平一级分类**，不是树形分类。分类没有父级、路径、层级或级联排序字段；管理端源码也明确提示“只可添加到一级”。

C01 需要复刻以下能力：

- 分类分页列表、固定字段、排序和文章数量；
- 启用分类全集；
- 分类新增、详情、编辑、启停和软删除；
- 已绑定文章时的删除保护；
- 分类状态在管理端、移动端分类入口和 PC 资讯中心的消费；
- 菜单、按钮和 API 权限。

Peanut 已沿用自身 ThinkPHP `Controller → Validator → Logic → Model` 分层与统一响应封装完成实现。权限方面只补齐具有写副作用的 `edit` 漏登记；共享只读接口 `all` 保持 LikeAdmin 的未登记放行语义。删除路径在不改变用户可见结果的前提下使用事务和行锁消除了参考实现的竞态。

## 2. 路由与调用链

LikeAdmin 使用 ThinkPHP 自动多应用路由，`url_route_must=false`，控制器名允许点号。管理端路径中的 `article.articleCate` 会由初始化中间件解析为 `article\ArticleCateController`。

```text
/adminapi/article.articleCate/{action}
  → InitMiddleware
  → LoginMiddleware
  → AuthMiddleware
  → ArticleCateController
      → ArticleCateValidate / ArticleCateLists
      → ArticleCateLogic
      → ArticleCate / Article
```

关键依据：

- `server/config/app.php:11-23`；
- `server/config/route.php:15-28`；
- `server/app/adminapi/config/route.php:15-27`；
- `server/app/adminapi/http/middleware/InitMiddleware.php:38-55`。

参考前端使用 GET 读取、POST 写入，但自动路由本身未声明 HTTP method 约束；Peanut 应按下表提供确定的方法契约。

## 3. 管理端 API 契约

| API | 方法 | 用途 | 参考权限字符 |
|---|---|---|---|
| `/adminapi/article.articleCate/lists` | GET | 分类分页列表 | `article.articleCate/lists` |
| `/adminapi/article.articleCate/all` | GET | 全部启用分类 | 未登记 |
| `/adminapi/article.articleCate/add` | POST | 新增分类 | `article.articleCate/add` |
| `/adminapi/article.articleCate/edit` | POST | 编辑分类 | **未登记，参考缺陷** |
| `/adminapi/article.articleCate/delete` | POST | 删除分类 | `article.articleCate/delete` |
| `/adminapi/article.articleCate/detail` | GET | 分类详情 | `article.articleCate/detail` |
| `/adminapi/article.articleCate/updateStatus` | POST | 修改状态 | `article.articleCate/updateStatus` |

### 3.1 列表

请求：

```text
GET /adminapi/article.articleCate/lists
page_no      默认 1
page_size    默认 25，最大 25000
page_type    默认 1；0 表示不分页并最多返回 25000 条
field        仅允许 create_time 或 id
order_by     仅允许 asc 或 desc
export       1 或 2 均不支持
```

这里的 25 是服务端未传参默认值。LikeAdmin 管理页面的分页组件会显式发送 `page_no=1&page_size=15`；2026-08-01 真实页面只读观察也得到该请求，不能把 UI 的 15 误写成服务端默认值。

分类列表没有业务筛选条件。即使传入 `name`、`is_show` 或其他参数，参考 `setSearch()` 也不会据此过滤。

默认顺序：

```text
sort DESC, id DESC
```

客户端只可用以下两组字段覆盖默认顺序：

```text
field=create_time&order_by=asc|desc
field=id&order_by=asc|desc
```

缺少排序参数、字段不受支持或排序方向无效时回退默认顺序。`sort` 本身不在客户端可选排序字段中。

列表行由活动的 `la_article_cate` 基础字段加 `article_count` 派生字段组成：

```text
id, name, sort, is_show,
create_time, update_time, delete_time,
article_count
```

- `article_count`：该分类下未软删除文章的数量；
- 已软删除分类不会出现在列表；
- 时间字段按 `Y-m-d H:i:s` 输出。

LikeAdmin 源码先调用 `append(['is_show_desc'])`，随后又调用 `append(['article_count'])`。ThinkPHP 后一次 `append()` 会覆盖前一次设置，因此真实运行响应只有 `article_count`，**不包含** `is_show_desc`。`ArticleCate` 模型中的 `getIsShowDescAttr()` 虽然存在，但没有进入最终列表序列化结果。Peanut 以真实运行契约为准，同样不输出该字段。

响应业务体：

```json
{
  "lists": [],
  "count": 0,
  "page_no": 1,
  "page_size": 25,
  "extend": []
}
```

LikeAdmin 外层为 `code=1, show=0, msg=''`。Peanut 保留自身全局 envelope，但业务字段、计数和顺序必须一致。

公共列表规则继续适用：

- `page_size > 25000`：`已超出系统限制数量，请分页查询或导出，当前最多记录数为：25000`；
- 非法页码、页大小、排序方向和时间参数由公共列表验证器拒绝；
- `export=1/2`：`该列表不支持导出`。

关键依据：

- `server/app/adminapi/lists/article/ArticleCateLists.php:27-97`；
- `server/app/common/lists/BaseDataLists.php:62-175`；
- `server/app/common/lists/ListsSortTrait.php:33-51`；
- `server/app/common/validate/ListsValidate.php:27-61`；
- `server/app/common/service/JsonService.php:119-142`；
- `server/config/project.php:46-50`。

### 3.2 启用分类全集

```text
GET /adminapi/article.articleCate/all
```

- 无请求参数和业务验证；
- 只返回 `is_show=1` 且未软删除的分类；
- 按 `sort DESC, id DESC`；
- 返回基础模型字段，不追加 `is_show_desc` 或 `article_count`；
- 不分页。

该接口供文章新增/编辑时选择分类。关键实现位于 `server/app/adminapi/application/article/ArticleCateLogic.php:119-125`。

### 3.3 新增

```text
POST /adminapi/article.articleCate/add
{
  "name": "分类名称",
  "sort": 0,
  "is_show": 1
}
```

| 字段 | 规则 |
|---|---|
| `name` | 必填，长度 1～90 |
| `is_show` | 必填，只允许 0 或 1 |
| `sort` | 可选，必须 `>= 0`，缺省 0；参考未限制整数和最大值 |

参考系统不校验分类名唯一，数据表也没有唯一索引，因此允许同名分类。

参考页面的数字控件额外限制 `sort` 最小 0、最大 9999，并提示“默认为0， 数值越大越排前”；这是页面输入边界，后端验证器仍只校验 `>=0`。

成功：`添加成功`。写入为单表 `create`，无事务。

### 3.4 详情与编辑

详情请求：

```text
GET /adminapi/article.articleCate/detail?id=1
```

- `id` 必填；
- 分类必须存在且未软删除；
- 返回该分类的基础模型字段。

编辑请求：

```text
POST /adminapi/article.articleCate/edit
{
  "id": 1,
  "name": "分类名称",
  "sort": 0,
  "is_show": 1
}
```

编辑使用完整验证规则：`id`、`name`、`is_show` 必填，`sort` 可选且 `>=0`。成功文案为 `编辑成功`。

编辑是无事务的单表更新。参考逻辑只在编辑分支捕获数据库异常，并把原始异常消息写入业务错误；新增、删除和状态修改没有同级捕获。

### 3.5 状态

```text
POST /adminapi/article.articleCate/updateStatus
{
  "id": 1,
  "is_show": 0
}
```

- `id` 必填且分类必须存在；
- `is_show` 必填，只允许 0 或 1；
- 成功文案：`修改成功`；
- 只更新分类本身，不级联修改文章状态。

状态值固定为：

```text
0 停用
1 启用
```

### 3.6 删除

```text
POST /adminapi/article.articleCate/delete
{"id": 1}
```

校验顺序：

1. 缺少 `id`：`资讯分类id不能为空`；
2. 分类不存在或已软删除：`资讯分类不存在`；
3. 存在任意未软删除的绑定文章：`资讯分类已使用，请先删除绑定该资讯分类的资讯`；
4. 校验通过后软删除分类，成功文案 `删除成功`。

已软删除文章不参与删除保护，也不计入 `article_count`。

参考实现先查询文章再执行分类软删除，期间没有事务、分类行锁或数据库外键，因此存在“检查通过后并发新增文章”的竞态。Peanut 已保持相同的用户可见删除规则，并使用事务和分类行锁消除竞态；没有改成级联删除文章。

## 4. 字段与数据模型

`la_article_cate` 的权威字段：

| 字段 | 类型/默认值 | 语义 |
|---|---|---|
| `id` | int，自增主键 | 分类 ID |
| `name` | varchar(90)，DB 可空 | 分类名称；API 强制非空 |
| `sort` | int，默认 0 | 数值越大越靠前 |
| `is_show` | tinyint，默认 1 | 0 停用，1 启用 |
| `create_time` | int，可空 | 创建时间 |
| `delete_time` | int，可空 | 软删除时间 |

表中没有：

- `pid`、`parent_id`、`level`、`path`；
- 分类名唯一索引；
- 分类与文章之间的数据库外键；
- 状态、排序或删除时间索引。

文章通过 `la_article.cid` 关联分类。模型关系为 `ArticleCate hasMany Article`，两张表均使用软删除。

关键依据：

- `server/public/install/db/like.sql:72-121`；
- `server/app/common/model/article/ArticleCate.php:25-68`；
- `server/app/common/model/article/Article.php:26-30`。

## 5. 层级、排序与页面语义

- 只支持一级分类；不存在新增子分类、移动节点、循环检测或父子状态规则；
- 管理端列表默认按 `sort DESC, id DESC`；
- 新增/编辑页面源码提示“数值越大越排前”；
- 同名、同排序值均允许；同排序值由 `id DESC` 稳定决定先后；
- 管理端列表显示栏目名称、文章数、状态、排序和操作。

### 5.1 LikeAdmin 真实页面只读观察

2026-08-01 已使用真实浏览器完成一次不写业务数据的参考页面观察：

- 菜单路径：`应用管理 → 文章资讯 → 文章栏目`；
- 页面地址：`/admin/app/article/column`；
- 顶部提示：`温馨提示：用于管理网站的分类，只可添加到一级`；
- 页面是普通平面表格，没有父级选择、树展开/折叠、子节点或移动操作；
- 列固定为：`栏目名称、文章数、状态、排序、操作`；
- 当时共 3 条：好物、生活、科技，均启用、sort=0、article_count=1；实际列表请求为 `page_no=1&page_size=15`；
- 新增弹窗标题为“新增栏目”，字段为栏目名称、排序、状态；空名称提交前提示 `请输入栏目名称`；
- 新增默认 sort=0、状态=1，sort 控件范围 0～9999；
- 编辑弹窗标题为“编辑栏目”，真实 `detail?id=3` 回填“好物”、sort=0、状态=1；
- 未提交新增或编辑，未点击删除或状态开关，网络记录中没有分类写请求；临时观察账号及其会话、关系和日志已精确清理为 0。

初始只读观察证据位于 `output/playwright/c01/reference-runtime/`；后续双系统隔离夹具已补齐新增、编辑、状态、删除、权限和异常写路径，最终证据位于 `output/playwright/c01/`。

前端源码依据：

- `admin/src/views/article/column/index.vue:3-65`；
- `admin/src/views/article/column/edit.vue:11-60`。

## 6. 状态消费闭环

分类状态不会改变已绑定文章，也不会成为文章查询和详情的强制关联条件。

| 消费位置 | 分类状态行为 |
|---|---|
| 管理端分类 `/lists` | 同时显示启用和停用分类 |
| 管理端分类 `/all` | 只返回启用分类 |
| 移动端 `/api/article/cate` | 只返回启用分类的 `id,name`，按 `sort DESC,id DESC` |
| PC `/api/pc/infoCenter` | 只聚合启用分类，分类按 `sort DESC,id DESC` |
| 移动端文章 `/api/article/lists?cid=` | 只校验文章自身 `is_show`，不校验分类状态 |
| 文章详情 | 只校验文章自身状态，不因分类停用自动失效 |

因此，停用分类的准确语义是“从分类入口和分类选择器中隐藏”，不是“下线分类下所有文章”。Peanut 不得把分类停用擅自实现为文章批量停用、级联删除或禁止直接访问文章。

Peanut 的 PC 消费路径已完成修复：`/api/pc/infoCenter` 调用文章查询时显式传入 `only_enabled_cate=true`，仅消费启用分类；普通 `/api/article/lists?cate_id=` 和文章详情仍只判断文章自身状态。因此停用分类会从 PC 资讯中心消失，但其活动文章仍可按分类 ID 查询和直接访问，与 LikeAdmin 的状态边界一致。

关键依据：

- `server/app/api/application/ArticleApplicationService.php:103-109`；
- `server/app/api/lists/article/ArticleLists.php:38-118`；
- `server/app/api/application/PcApplicationService.php:191-205`；
- `server/app/common/model/article/Article.php:94-109`。

## 7. 权限契约与参考缺陷

安装 SQL 的菜单关系：

| 菜单 ID | 类型 | 名称 | 权限字符 |
|---:|---|---|---|
| 70 | M | 文章资讯 | 空 |
| 73 | C | 文章栏目 | `article.articleCate/lists` |
| 78 | A | 添加 | `article.articleCate/add` |
| 79 | A | 删除 | `article.articleCate/delete` |
| 80 | A | 详情 | `article.articleCate/detail` |
| 81 | A | 修改状态 | `article.articleCate/updateStatus` |

权威 SQL 位于 `server/public/install/db/like.sql:648`。

### 7.1 `edit` 漏登记

参考系统存在明确的不一致：

- 编辑 API 和前端调用真实存在；
- 前端编辑按钮检查 `article.articleCate/edit`；
- 安装菜单没有登记该权限字符；
- 权限中间件只拦截“已登记但未授权”的 URI，未登记 URI 直接放行。

结果：普通角色通常看不到编辑按钮，但已登录普通管理员可绕过页面直接调用编辑 API。这是权限缺陷，不是应复刻的业务能力。

### 7.2 `all` 未登记（保留的共享只读语义）

`article.articleCate/all` 没有菜单权限记录，因此任意已登录管理员都会被中间件放行。它被 C02 文章新增/编辑页面用作分类下拉共享数据源，而文章分类页面本身不调用它。

该接口跨 C01 分类页与 C02 文章页共享。若把它挂到分类页面权限，只有文章管理权限的角色会加载分类下拉失败；若挂到文章页面，又会限制其他需要读取分类的角色。因此 Peanut 明确保留 LikeAdmin 的“登录后未登记放行”只读语义，不为 `all` 创建按钮权限节点。这不是遗漏，也不应在撤权验收中期望 403。

### 7.3 Peanut 最终实现

Peanut 已完成：

1. 保持参考已登记的 `lists/add/delete/detail/updateStatus` 权限；
2. 新增显式 `article.articleCate/edit` 写权限登记，并让编辑按钮与编辑 API 使用同一权限字符；
3. `article.articleCate/all` 不登记权限节点，登录后按现有“未登记 URI 放行”规则提供共享只读数据；
4. 撤销分类权限后，分类菜单、页面、add/edit/delete/status 按钮、直达路由及已登记 API 同时失效，但 `all` 在两端仍放行；
5. 只把 `edit` 漏登记的修复记录为安全修正，不把 `all` 的保留语义误报为权限缺陷。

参考权限中间件依据：`server/app/adminapi/http/middleware/AuthMiddleware.php:40-75`。

### 7.4 Peanut 权限实装 ID

Peanut 当前权威数据库与全新安装脚本使用以下全局唯一 ID；主键无需与 LikeAdmin 安装 SQL 相同，权限字符和父子关系才是跨系统契约：

| 菜单 ID | 类型 | 名称 | 权限字符 |
|---:|---|---|---|
| 45 | M | 内容管理 | 空 |
| 46 | C | 文章分类 | `article.articleCate/lists` |
| 47 | A | 分类新增 | `article.articleCate/add` |
| 48 | A | 分类编辑 | `article.articleCate/edit` |
| 49 | A | 分类删除 | `article.articleCate/delete` |
| 78 | A | 分类详情 | `article.articleCate/detail` |
| 79 | A | 分类状态 | `article.articleCate/updateStatus` |

原先发现的跨域菜单 ID 冲突已修复，幂等迁移已应用到权威数据库：六个有权限字符的接口各登记一条，旧 slash 权限为 0，`all` 权限节点为 0。普通角色授权、撤权、缓存失效和恢复均已通过真实运行验收。

## 8. 验证、异常和原子性

### 8.1 固定错误语义

| 场景 | 文案 |
|---|---|
| id 缺失 | `资讯分类id不能为空` |
| 分类不存在 | `资讯分类不存在` |
| 名称缺失 | `资讯分类不能为空` |
| 名称长度不在 1～90 | `资讯分类长度须在1-90位字符` |
| sort 小于 0 | `排序值不正确` |
| `is_show=2` | `is_show必须在 0,1 范围内` |
| 分类仍被文章使用 | `资讯分类已使用，请先删除绑定该资讯分类的资讯` |
| 列表导出 | `该列表不支持导出` |

`is_show` 的枚举错误文案已由双系统真实 API 固化；两端均拒绝非法状态且不产生业务行。

### 8.2 事务边界

参考 C01 所有写操作都是单表写，没有事务：

- add：单次 create；
- edit：单次 update，独自捕获异常；
- updateStatus：单次 update；
- delete：应用层检查文章后软删除分类。

Peanut 的删除实现已使用数据库事务：先对目标分类行加锁，再在同一事务内对活动关联文章查询加锁；存在文章时回滚并返回参考文案，不存在时软删除分类后提交。该实现消除了 LikeAdmin “检查后并发新增文章”的竞态，同时没有引入级联删除、树结构或额外发布状态。

## 9. 最终结论与差异

C01 已完成双系统真实页面、API、权限、状态消费、数据库不变量和精确清理验收，无剩余业务功能差异。最终结论如下：

- 两端均为扁平一级分类，列表字段、分页、无筛选、排序、文章计数、新增、详情、编辑、启停和软删除语义一致；
- LikeAdmin 连续两次 `append()` 导致 `is_show_desc` 在真实列表响应中被覆盖，Peanut 已按运行结果移除该字段；
- 非法 `is_show=2` 的运行文案固定为 `is_show必须在 0,1 范围内`，所有失败请求前后业务数据不变；
- 移动端分类入口和 PC 资讯中心只消费启用分类，停用分类不级联修改文章，直接文章列表和详情仍可访问活动文章；
- Peanut 显式登记 `edit` 写权限，修复 LikeAdmin 的漏登记缺陷；`all` 在两端均保持登录后未登记放行；
- Peanut 以事务和行锁强化删除原子性，这是不改变用户可见业务结果的并发安全修正；
- 前端框架、全局响应 envelope 和菜单主键不同属于已接受实现差异，不影响业务契约；
- 双端隔离分类、文章、临时管理员、角色、关系、会话、日志和认证缓存均已精确清理为 0，非夹具业务指纹和权威菜单保持不变。

最终证据：

- `output/playwright/c01/baseline/likeadmin.json`、`output/playwright/c01/baseline/peanut.json`；
- `output/playwright/c01/business/likeadmin.json`、`output/playwright/c01/business/peanut.json`；
- `output/playwright/c01/state-consumption/likeadmin.json`、`output/playwright/c01/state-consumption/peanut.json`；
- `output/playwright/c01/permission/likeadmin.json`、`output/playwright/c01/permission/peanut.json`；
- `output/playwright/c01/invariants/likeadmin.json`、`output/playwright/c01/invariants/peanut.json`；
- `output/playwright/c01/cleanup/likeadmin.json`、`output/playwright/c01/cleanup/peanut.json`。

## 10. 最低充分验收矩阵

每项只执行一次能够直接证明结论的最小验收。夹具必须使用独立前缀，验收结束后精确清理。

| 编号 | 验收项 | 最低充分证据 | 当前状态 |
|---|---|---|---|
| C01-01 | 列表字段、默认顺序和文章数 | 两端创建两条不同 sort 分类及一篇绑定文章；真实页面与 API 字段、顺序、count 对比；列表均不输出 `is_show_desc` | 已验收 |
| C01-02 | 分页、无筛选和禁止导出 | 两端一次分页切换；传入无效业务筛选不改变结果；`export=1` 返回禁止导出 | 已验收 |
| C01-03 | 新增与验证 | 两端新增一次；缺 name、91 字符 name、负 sort、非法状态各一次；核对默认 sort=0 | 已验收 |
| C01-04 | 详情与编辑 | 两端详情回显后编辑 name/sort/status；不存在 id 的错误语义一致 | 已验收 |
| C01-05 | 状态与消费 | 停用分类后管理列表仍在，`all`、移动端 cate、PC infoCenter 均消失；直接文章列表/详情不级联失效 | 已验收 |
| C01-06 | 删除约束与软删除 | 绑定活动文章时删除被拒；文章软删后分类可软删；列表、详情与 all 不再返回 | 已验收 |
| C01-07 | 扁平结构与排序 | 页面无父级入口；相同 sort 按 id DESC，不存在树操作或级联状态 | 已验收 |
| C01-08 | 普通角色权限 | 授权/撤权各一次，核对菜单、add/edit/delete/status 按钮、直达与 API；Peanut 仅补登记 edit，all 在两端均保持登录后放行 | 已验收 |
| C01-09 | 数据模型与异常不变量 | DB 核对字段、软删除、未级联文章、无意外重复/孤儿副作用；失败请求前后数据不变 | 已验收 |
| C01-10 | 精确清理 | 两端分类、文章、临时管理员/角色/权限/会话和日志联合计数为 0 | 已验收 |

C01 已关闭，后续批次不得重复执行上述业务验收。

## 11. 关键参考文件

- `server/app/adminapi/controller/article/ArticleCateController.php`；
- `server/app/adminapi/validate/article/ArticleCateValidate.php`；
- `server/app/adminapi/lists/article/ArticleCateLists.php`；
- `server/app/adminapi/application/article/ArticleCateLogic.php`；
- `server/app/common/model/article/ArticleCate.php`；
- `server/app/common/model/article/Article.php`；
- `server/app/adminapi/http/middleware/AuthMiddleware.php`；
- `server/app/api/controller/ArticleController.php`；
- `server/app/api/application/ArticleApplicationService.php`；
- `server/app/api/lists/article/ArticleLists.php`；
- `server/app/api/application/PcApplicationService.php`；
- `server/public/install/db/like.sql`；
- `admin/src/api/article.ts`；
- `admin/src/views/article/column/index.vue`；
- `admin/src/views/article/column/edit.vue`。
- `output/playwright/c01/reference-runtime/README.md`；
- `output/playwright/c01/reference-runtime/runtime-observation.json`。
