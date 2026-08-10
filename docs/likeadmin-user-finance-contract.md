# LikeAdmin 1.9.4 用户与财务契约

> **历史归档：** 本文是 parity 实施阶段的契约快照；相关任务现已全部完成。文中的 `.workspace/likeadmin` 绝对路径属于已删除的旧参考运行目录，不是当前工作目录或仍存在的事实源。

> 任务：U01、U02、F01、F02  
> 状态：U01、U02、F01 已实现并验收；F02 待实施

## 1. 总体结论

Peanut 已完成 U01 用户模型与列表、U02 用户详情与余额调整，以及 F01 账户流水模型、查询、动态字典、权限和页面展示。`user_money` 已作为权威余额，平台增减分别写入 200/100 类型日志，并保留事务与行锁。F02 的首次退款、失败重试、支付渠道调用、异步/同步回调和余额联动仍待实施。

实施依赖必须保持：

```text
U01 用户数据模型与列表
  → U02 用户详情、编辑与余额日志地基
    → F01 余额明细
      → F02 充值与退款状态机
```

## 2. U01 用户列表

### LikeAdmin 契约

- 筛选：`keyword`、`create_time_start`、`create_time_end`、`channel`；
- keyword 匹配 `sn/nickname/mobile/account`；
- channel 枚举：1 微信小程序、2 公众号、3 手机 H5、4 PC、5 iOS、6 Android；
- 服务端分页和导出；
- 列表字段：`id,sn,nickname,sex,avatar,account,mobile,channel,create_time`；
- 表格展示头像、昵称、账号、手机号、注册来源、注册时间；
- 详情入口权限为 `user.user/detail`。

参考证据：

- `/Users/xing/Documents/company-projects/peanut-admin/.workspace/likeadmin/admin/src/views/consumer/lists/index.vue`；
- `/Users/xing/Documents/company-projects/peanut-admin/.workspace/likeadmin/server/app/adminapi/lists/user/UserLists.php`；
- `/Users/xing/Documents/company-projects/peanut-admin/.workspace/likeadmin/server/app/common/model/user/User.php`；
- `/Users/xing/Documents/company-projects/peanut-admin/.workspace/likeadmin/server/app/common/enum/user/UserTerminalEnum.php`。

### Peanut U01 实现

- `pa_member` 已补齐 `account/password/real_name/channel/login_time/login_ip/is_new_user/total_recharge_amount`，并增加 account、channel、create_time 查询索引；
- `MemberController → MemberLogic → Member` 保持 Peanut 既有 ThinkPHP 分层；列表按 `id desc`，keyword 同时模糊匹配 `sn/nickname/mobile/account`，channel 精确匹配，注册时间按闭区间过滤；
- 列表响应包含参考核心字段，并追加 `sex_value/channel_value/is_disable/user_money` 兼容字段；密码由模型隐藏，头像继续使用公共文件 URL 转换；
- `export=1` 返回与参考相同的导出信息，`export=2` 按全部/分页范围生成 XLSX；默认文件名 `用户列表`，单次上限 25,000 条；
- 导出列固定为用户编号、用户昵称、账号、手机号码、注册来源、注册时间；
- 前端展示参考筛选和核心列，使用服务端 `page_no/page_size`；后台新增、编辑、状态、余额和标签作为 Peanut 扩展保留，不代替参考能力；
- `member/add`、`member/edit` 已显式登记按钮/API 权限，不能依赖未登记 URI 放行。

### U01 验收证据（2026-07-29）

- 两库各插入 16 条同语义临时用户，在真实浏览器分别用 account、nickname、mobile、sn 四个唯一值查询，均只命中对应行；
- 两端组合请求均实际携带 `keyword=72910004`、`channel=6`、`create_time_start=2026-07-23 00:00:00`、`create_time_end=2026-07-23 23:59:59`，返回同一安卓 APP 用户；
- 两端默认每页 15 条，均从第 1 页切到第 2 页并显示第 16 条用户，网络请求为 `page_no=2&page_size=15`；
- 两端导出弹窗均显示 16 条、2 页、最多 25,000 条，并实际完成 `export=1`、`export=2` 两阶段请求；
- Peanut 下载 `.playwright-mcp/用户列表-20260729-134059-61b233.xlsx`，LikeAdmin 下载 `.playwright-mcp/用户列表-2026-07-29-134952.xlsx`，两份文件均通过 ZIP/OpenXML 完整性检查；
- 两库各 16 条临时用户均已精确删除并核验剩余 0；LikeAdmin 管理员原密码哈希及 `HOSTNAME = "mysql"` 均已恢复。

## 3. U02 用户详情与编辑

详情 payload 必须覆盖：

```text
id, sn, account, nickname, avatar, real_name, sex, mobile,
create_time, login_time, channel, user_money
```

可编辑字段只允许 `account/sex/mobile/real_name`：

- account 唯一；
- mobile 格式正确且唯一；
- 使用 `{id,field,value}` 的单字段更新语义；
- nickname、渠道、注册时间、最近登录等按参考只读展示。

### Peanut U02 实现

- 详情响应覆盖 `id,sn,account,nickname,avatar,real_name,sex,mobile,create_time,login_time,channel,user_money`；
- 按 LikeAdmin 使用 `{id,field,value}` 单字段更新，只开放 `account/sex/mobile/real_name`；账号、手机号执行唯一性校验，手机号执行格式校验；
- 为保持 LikeAdmin 实际后端语义，`sex` 单字段接口不额外限制为 0/1/2，前端仍只提供 0/1/2；
- 状态接口先校验 `id/status`，更新异常按 Peanut 既有 Logic 错误返回；
- 编辑和余额弹窗在业务失败后解除 loading，允许用户在同一弹窗修正后再次提交。

### U02 编辑与状态验收证据（2026-08-01）

- 重复账号返回“账号已被使用”，合法账号由 `u02parity_a` 更新为 `u02parity_ok`；
- 真实姓名由“验收前”更新为“验收姓名”；
- 后端接受 LikeAdmin 同语义的非枚举 sex，随后通过 UI 恢复为 `1/男`；
- 非法手机号返回“手机号码格式错误”，同一弹窗随后成功提交 `13800139102`；
- 状态真实完成 `1 → 0 → 1`；
- 清理前最终用户数据为 `id=17, account=u02parity_ok, real_name=验收姓名, sex=1, mobile=13800139102, status=1`。

## 4. U02 余额调整与账户日志

### 请求与校验

```json
{
  "user_id": 1,
  "action": 1,
  "num": 100,
  "remark": "调整原因"
}
```

- action：1 增加、2 扣减；
- num 必须大于 0；
- remark 最长 128；
- 扣减后余额不得小于 0；
- 保留 Peanut 当前事务和行锁，避免并发余额覆盖。

### 账户日志

参考分类模型：

| change_type | 语义 | action |
|---:|---|---:|
| 100 | 平台扣减余额 | 2 |
| 101 | 充值退款扣减 | 2 |
| 200 | 平台增加余额 | 1 |
| 201 | 用户充值增加 | 1 |

日志字段至少包含流水号 `sn`、变动对象/类型/action、无符号金额、变后余额、来源单号 `source_sn`、备注、扩展数据和时间。

### Peanut U02 实现

- 幂等迁移仅在首次新增 `user_money` 时从兼容字段 `balance` 初始化，重跑迁移不会覆盖权威余额；
- 余额调整在事务内锁定会员行，使用当前 ThinkORM 支持的 `lock(true)`，扣减后余额不得小于 0；
- 平台扣减写 `change_type=100, action=2`，平台增加写 `change_type=200, action=1`；
- 日志已记录唯一流水号、变动对象、类型、action、无符号金额、变后余额、来源类型与单号、备注、扩展数据、管理员和时间；兼容字段 `after_amount` 与权威 `left_amount` 同步。

### U02 余额验收证据（2026-08-01）

- 超额扣减返回“用户可用余额仅剩100.00”，失败后同一弹窗仍可继续提交；
- 增加 `25.50` 后余额为 `125.50`，扣减 `10.25` 后余额为 `115.25`；
- 清理前恰好两条流水：`change_type=200, action=1, change_amount=25.50, left_amount=125.50` 与 `change_type=100, action=2, change_amount=10.25, left_amount=115.25`；
- 两条流水均满足 `change_object=1`、`after_amount=left_amount`、`source_type=0`、`source_sn=''`、`extra=''`、`admin_id=1`，流水号非空且互不相同；
- 清理前最终 `user_money=balance=115.25`，`information_schema.innodb_trx=0`，无遗留事务。

### U02 权限、清理与浏览器证据（2026-08-01）

- 普通管理员真实浏览器会话可见会员列表和详情；新增、禁用、编辑和余额调整入口均未渲染；
- 直接 POST `user.user/edit`、`user.user/adjustMoney`、`member/status` 均返回 `code=40300,msg=暂无访问权限`；
- 三次权限拒绝后，用户字段、状态、余额和两条账户流水均保持不变；
- 临时管理员 `id=10`、角色 `id=7`、会员 `id=17/18` 及其会话、角色关系、账户流水和标签关系已精确清理，逐项核验剩余 0；
- 前端执行 `npx vue-tsc --noEmit --skipLibCheck`，类型检查通过；
- LikeAdmin 证据：`.playwright-cli/likeadmin-u02-final.png`、`.playwright-cli/page-2026-07-29T07-22-15-952Z.yml`；
- Peanut 主业务证据：`output/playwright/peanut-u02-final.png`；
- Peanut 普通角色证据：`.playwright-cli/page-2026-08-01T05-04-59-292Z.png`、`.playwright-cli/network-2026-08-01T05-05-04-738Z.log`。

## 5. F01 余额明细

### API 与查询契约

标准接口：

- `GET /adminapi/finance.account_log/lists`；
- `GET /adminapi/finance.account_log/getUmChangeType`。

查询规则：

- `user_info` 模糊匹配用户 `sn/nickname/mobile/account`；
- `change_type` 精确筛选；
- `start_time/end_time` 使用 `YYYY-MM-DD HH:mm:ss` 和闭区间；`end_time <= start_time` 返回“搜索的时间范围不正确”；
- `page_no/page_size` 为服务端分页参数；`page_type=0` 时最多返回 25,000 条；
- 仅当额外传入 `type=um` 时限制为 100/101/200/201，参考余额明细页面本身不传该参数；
- `export=1/2` 均返回“该列表不支持导出”。

动态字典为：

| change_type | 文案 |
|---:|---|
| 100 | 平台减少余额 |
| 101 | 充值订单退款减少余额 |
| 200 | 平台增加余额 |
| 201 | 充值增加余额 |

`getUmChangeType` 在参考系统中未登记权限字符，任意已登录管理员均可访问。

### 列表与数据模型契约

响应行固定包含：

```text
nickname, account, sn, avatar, mobile,
action, change_amount, left_amount,
change_type, change_type_desc, source_sn, create_time
```

- `sn` 是用户编号，不是账户流水号；
- `change_amount` 根据 action 带 `+/-` 并保留两位小数；
- `create_time` 是格式化时间字符串；
- 查询使用用户 inner join，软删除流水和孤儿流水不显示；
- `source_sn` 必须完整返回，不能按旧 64 字符长度截断。

### Peanut F01 实现

- 保持 Peanut 既有 Controller、Validator、Logic、Model 分层；
- 增加 dotted 标准路由，并保留现有 REST 兼容路由；
- `MemberBalanceLog` 接入 `BaseModel + SoftDelete`，流水号生成使用 `withTrashed()` 避免与软删除记录冲突；
- `pa_member_balance_log` 补齐 `source_sn varchar(255)`、`remark varchar(255)`、`update_time` 和 `delete_time`；
- 增加余额明细菜单和 `finance.account_log/lists` 权限字符；
- 前端接入标准 dotted API，提供用户信息、动态变动类型、精确到秒时间区间、参考列和服务端分页，不提供参考系统不支持的导出入口；
- 前端执行 `npx vue-tsc --noEmit --skipLibCheck`，类型检查通过。

首次真实页面验证发现 `create_time` 被二次格式化为 `1970-01-01 08:33:46`：ThinkPHP 模型已经把时间戳转换成字符串，Logic 又把字符串强制转成整数后调用 `date()`。现已最小修复为仅对 numeric 值执行 `date()`，已格式化字符串直接透传；PHP lint 和一次真实浏览器复验均通过。

### F01 双系统验收证据（2026-08-01）

隔离主用户 `f01parity_main` 在两端均返回 4 条可见流水：

| change_type | change_amount | left_amount | create_time |
|---:|---:|---:|---|
| 101 | -5.00 | 140.25 | 2026-08-02 08:00:00 |
| 201 | +30.00 | 145.25 | 2026-08-01 21:00:00 |
| 100 | -10.25 | 115.25 | 2026-08-01 20:00:00 |
| 200 | +25.50 | 125.50 | 2026-08-01 07:59:59 |

双端真实浏览器 API 矩阵结果一致：

- 动态字典均返回 100/101/200/201 四项，四种类型精确筛选均 `count=1`；
- account、nickname、mobile、sn 四种 `user_info` 均 `count=4`；
- `20:00:00 ～ 21:00:00` 闭区间均 `count=2`；
- `page_no=2&page_size=2` 均为总数 4、当前页 2 条；
- 未知类型和孤儿用户均返回 0 条；
- 等时非法范围和 `export=1` 分别返回约定错误文案；
- `type=um` 均返回 4 条，12 个响应字段完全一致，128 字符 `source_sn` 均完整返回；
- `page_type=0` 时 Peanut 为 5 条、LikeAdmin 为 7 条，差额来自参考库两条既有 U02 流水；两端 `page_size` 均为 25,000，不属于查询规则差异。

Peanut 全局响应 envelope 使用 `20000/40000`，LikeAdmin 使用 `1/0`；这是 Peanut 既有全局协议，不属于 F01 字段或业务逻辑差异。

权限验收使用两端同语义普通角色：授权父/叶菜单时余额明细菜单和列表均可见；撤销叶权限后菜单消失。Peanut 列表返回 `40300 / 暂无访问权限`，LikeAdmin 返回 `0 / 权限不足，无法访问或操作`；两端未登记权限的字典接口仍成功返回四项。权限请求前两端均恰好保留 7 条固定流水和 2 个夹具用户，证明拒绝请求未改变业务数据。

清理时两端各精确删除 7 条固定流水和 2 个夹具用户，并删除本轮临时管理员、角色、菜单关系和会话；两端流水、用户、管理员、角色、角色关系、菜单关系和会话核验均为 0，未触碰 U02 数据。

证据：

- `output/playwright/peanut-f01-main.png`；
- `output/playwright/likeadmin-f01-main.png`；
- `output/playwright/peanut-f01-network.log`；
- `output/playwright/likeadmin-f01-network.log`；
- `output/playwright/peanut-f01-permission-network.log`；
- `output/playwright/likeadmin-f01-permission-network.log`。

## 6. F02 充值列表

### 筛选和列表

- `sn,user_info,pay_way,pay_status,start_time,end_time`；
- 服务端分页和导出；
- 行字段：`id,sn,order_amount,pay_way,pay_time,pay_status,create_time,refund_status,avatar,nickname,account` 及文案字段；
- pay_way：1 余额、2 微信、3 支付宝；
- pay_status：0 未支付、1 已支付；
- 只有已支付记录可退款，已发起退款时按钮禁用。

当前 Peanut pay_way/status 枚举、表字段和筛选不同，且没有退款动作。`pa_recharge_order` 需要补齐 pay_sn、order_terminal、refund_status、refund_transaction_id、软删除等字段。

## 7. F02 首次退款

请求：`{recharge_id}`。

资格校验：

- 充值订单存在；
- pay_status=1；
- refund_status=0；
- 用户余额足以扣除本次全额退款。

一个事务内只执行一次：

1. 充值单标记 refund_status=1；
2. 扣减用户 `total_recharge_amount`；
3. 扣减 user_money；
4. 写充值退款账户日志并关联 source_sn；
5. 创建 refund_record；
6. 创建 refund_log；
7. 调用公共支付退款。

支付结果：

- 微信请求成功后保持处理中，等待查询任务/回调；
- 支付宝可同步成功并更新 log/record/充值退款交易号；
- 支付 API 异常将 log/record 标记为失败并记录原因；
- 全流程需要幂等，不能重复扣余额或重复创建 record。

## 8. F02 失败重试

请求：`{record_id}`。

- refund_record 必须存在且不是成功；
- 复用同一 record；
- 每次重试新建一条 refund_log；
- 只重试支付渠道，不重复扣用户余额、不重复扣累计充值、不新建 refund_record。

## 9. 退款记录、统计和日志

- 统计卡：total/ing/success/error 金额；
- tabs 的各状态计数；
- 筛选：sn/order_sn/user_info/refund_type/date/status；
- 列表：sn、用户、order_sn、refund_amount、类型、状态、时间；
- 失败行显示“重新退款”；
- 所有行可查看日志；
- 日志字段：sn、refund_amount、status、time、handler，最新在前。

当前 Peanut 退款页存在三处重复解包：Axios 拦截器已经返回 envelope，页面又读取 `res.data.data`，导致统计、列表和日志得到 `undefined`。这是 F02 的首个独立修复项。

## 10. 权限字符

完整权限至少包括：

- 用户：lists/detail/edit/adjustMoney；
- 余额明细：lists；类型字典按参考为未登记 URI；
- 充值：lists/refund/refundAgain；
- 退款：record/log；stat 按参考为未登记 URI。

A03 已将后端权限语义对齐为“未登记 URI 直接放行，已登记才检查角色”，但菜单/按钮种子和前端按钮指令仍必须补齐，不能依靠未登记放行代替明确授权。

U02 已用普通管理员真实浏览器会话验证：只授权列表和详情时，页面不渲染新增、禁用、编辑、余额调整入口；直接请求三个未授权写接口均返回 `40300`，且业务数据未发生变化。

## 11. 参考实现异常

以下是 LikeAdmin 1.9.4 可观察的源码现状，实施时不得静默归一：

1. 首次退款账户日志传入 change_type=200 但 action=扣减；枚举另有正确的充值退款扣减 101，可能显示错误描述。
2. 失败重试不再次扣余额，却再次校验当前余额是否大于原充值金额，可能无意义阻断重试。
3. 失败处理尝试写 refund_record.refund_msg，但参考安装 SQL 没有该列。
4. 退款统计按 order_amount 而非 refund_amount 汇总；全额退款时两者相同。

1:1 基线默认以参考系统实际行为为事实；若某异常会导致目标能力不可用，需要在 F02 实施前记录明确的兼容决策和最终差异，不能悄悄修复或自行改业务口径。

## 12. 最小验收顺序

1. U01：同筛选输入得到同字段、分页和导出结果。
2. U02：四个可编辑字段分别验证一条成功/失败规则；余额增减各一次并核对日志与余额。
3. F01：按用户、类型、日期查询同一笔余额日志。
4. F02：已支付充值单首次退款一次；失败记录重试一次；核对余额只扣一次、record 一条、log 递增和最终状态。
5. 普通角色分别验证菜单、按钮和 API 权限；每项已有充分证据后停止重复验证。
