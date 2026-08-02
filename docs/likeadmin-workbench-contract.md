# LikeAdmin 1.9.4 工作台契约

> 任务：D01  
> 状态：已实现并验收

## 1. 关键结论

LikeAdmin 1.9.4 工作台不是生产数据统计：今日销售、订单、用户和访问量是固定演示值；15 日访问趋势和 7 日销售趋势在每次请求时随机生成。除平台名称外，不读取用户、充值、订单或访问日志表。

Peanut Admin 要求 1:1 复刻其实现逻辑，因此 D01 不得擅自改为 `pa_member`、`pa_recharge_order` 等表的真实聚合。若未来要引入真实经营看板，应作为复刻完成后的独立产品变更。

## 2. API 契约

Peanut 路径按现有规范定义为：

```http
GET /api/admin/workbench/index
Authorization: Bearer <token>
```

返回保持 Peanut envelope：

```json
{
  "code": 20000,
  "msg": "success",
  "data": {
    "version": {},
    "today": {},
    "menu": [],
    "visitor": {},
    "support": [],
    "sale": {}
  }
}
```

`data` 必须固定包含六个顶层键：

```ts
type VersionInfo = {
  version: string
  website: string
  name: string
  based: string
  channel: { website: string; gitee: string }
}

type TodayMetrics = {
  time: string
  today_sales: number
  total_sales: number
  today_visitor: number
  total_visitor: number
  today_new_user: number
  total_new_user: number
  order_num: number
  order_sum: number
}

type Shortcut = { name: string; image: string; url: string }
type TrendSeries = {
  date: string[]
  list: Array<{ name: string; data: number[] }>
}
type Support = { image: string; title: string; desc: string }
```

参考源码：`/Users/xing/Documents/company-projects/peanut-admin/.workspace/likeadmin/server/app/adminapi/controller/WorkbenchController.php`、`/Users/xing/Documents/company-projects/peanut-admin/.workspace/likeadmin/server/app/adminapi/logic/WorkbenchLogic.php`。

## 3. version

- `version`：后端项目版本配置；
- `website`：Peanut 自有网站配置；
- `name`：读取网站名称配置；
- `based`：按相同字段语义写 Peanut 技术栈；
- `channel`：Peanut 自有渠道地址；未确定时返回空串，前端不渲染伪链接。

这里保持参考的数据来源逻辑，但不得继续显示 LikeAdmin 品牌或热链其站点。

## 4. today 固定值

每次请求只动态生成服务器当前时间，指标保持参考固定值：

| 字段 | 值 |
|---|---:|
| today_sales | 100 |
| total_sales | 1000 |
| today_visitor | 10 |
| total_visitor | 100 |
| today_new_user | 30 |
| total_new_user | 3000 |
| order_num | 12 |
| order_sum | 255 |

`time = date('Y-m-d H:i:s')`，使用现有 `Asia/Shanghai` 时区。

## 5. visitor 与 sale

`visitor`：

- 15 个点，顺序为今天到 14 天前；
- 日期格式 `m/d`；
- 每点为包含边界的 `rand(0,100)`；
- list 只有一项，名称 `访客数`。

`sale`：

- 7 个点，顺序为今天到 6 天前；
- 每点为包含边界的 `rand(30,200)`；
- list 只有一项，名称 `销售量`。

参考页面将日期 reverse，但没有同步 reverse data，存在可观察的日期和值反向错配。严格复刻时保留当前行为；如决定修复，必须在最终差异报告中单列，不能静默改变。

## 6. 快捷入口

固定返回八项且不按当前管理员权限过滤，顺序为：

| 顺序 | 名称 | Peanut 路由 |
|---:|---|---|
| 1 | 管理员 | `/system/admin` |
| 2 | 角色管理 | `/system/role` |
| 3 | 部门管理 | `/system/dept` |
| 4 | 字典管理 | `/system/dict` |
| 5 | 代码生成器 | `/dev-tools/code`（依赖未完成） |
| 6 | 素材中心 | `/system/file` |
| 7 | 菜单权限 | `/system/menu` |
| 8 | 网站信息 | `/system/config` |

参考接口始终返回全部入口，目标路由自行做权限拦截。代码生成器入口在对应业务完成前标记为依赖，不能据此声称工作台全闭环。

## 7. 权限

- 工作台 API 必须放入完整 Login → Auth → OperationLog 管理 API 组；
- 权限字符为 `workbench/index`；
- root 放行；普通角色只有授权后才能访问；
- 当前 Peanut 前端工作台配置 `roles=['*']` 与参考的可分配权限不一致，由 A03 动态菜单/按钮权限任务统一修复。

## 8. 实施文件边界

低冲突独立文件：

- `server/app/adminapi/controller/WorkbenchController.php`；
- `server/app/adminapi/logic/WorkbenchLogic.php`；
- `server/config/project.php`；
- `web/src/api/workbench.ts`；
- `web/src/views/dashboard/workplace/index.vue`；
- 工作台 locale 文件。

共享文件 `server/route/app.php`、`server/database/init.sql` 由主线统一集成。

## 9. 最小验收

1. 一次 API 验收：六个顶层键；today 八个固定指标；visitor 15 点且范围 0—100；sale 7 点且范围 30—200；menu 8 项；support 2 项。
2. 一次真实浏览器验收：版本、四组指标、八个快捷入口、两张趋势图可见；点击一个已存在的快捷入口验证路由。
3. 普通角色的未授权/授权结果纳入 A03 权限验收，不重复创建额外测试角色。

## 10. 验收记录

- 2026-07-28：`/api/admin/workbench/index` 返回 code=20000，六个顶层键完整；today 固定值正确；visitor 15 点均在 0—100；sale 7 点均在 30—200；menu 8 项；support 2 项。
- 2026-07-28：真实浏览器打开 `/dashboard/workplace`，版本、四组今日指标、八个快捷入口、两张趋势图和两个支持项均成功渲染。
- 工作台 M/C 菜单及 `workbench/index` 权限已进入当前数据库、增量迁移和全新安装脚本。
- 普通角色的授权前/授权后结果归入 A03/O03 联合验收，不在 D01 重复创建测试角色。
