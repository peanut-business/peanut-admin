# LikeAdmin 1.9.4 充值与退款契约

> 任务：F02 充值与退款业务  
> 状态：已完成（历史 F02 单次全额退款基线），2026-08-01 双系统验收与隔离夹具清理通过
>
> 部分/多次退款扩展：实现中（数据库资格阻塞，暂后置）；不属于下方 F02 通过项。
> 契约来源：LikeAdmin 1.9.4 后端、前端、安装 SQL 与退款查询命令的 CodeGraph/源码只读盘点

## 1. 实施边界与结论

F02 复刻以下业务能力：

- 充值记录筛选、分页、导出和退款入口；
- 已支付充值单的首次全额退款；
- 失败退款基于同一退款记录重试；
- 退款记录、状态统计和每次渠道尝试日志；
- 用户余额、累计充值金额和账户流水的一次性联动；
- 微信异步退款确认、支付宝同步退款结果和支付异常处理；
- 菜单、按钮和 API 权限。

Peanut 保持现有 ThinkPHP `Controller → Validator → Logic → Model/Service` 分层和全局响应码，不复制会造成重复扣款、假成功或记录丢失的参考缺陷。所有有意偏离 LikeAdmin 缺陷的安全决策均在本文明确记录。

## 2. API 与权限契约

| API | 方法 | 权限字符 | 用途 |
|---|---|---|---|
| `/adminapi/recharge.recharge/lists` | GET | `recharge.recharge/lists` | 充值记录 |
| `/adminapi/recharge.recharge/refund` | POST | `recharge.recharge/refund` | 首次全额退款（历史基线） |
| `/adminapi/recharge.recharge/refundAgain` | POST | `recharge.recharge/refundAgain` | 失败重试 |
| `/adminapi/finance.refund/record` | GET | `finance.refund/record` | 退款记录 |
| `/adminapi/finance.refund/log` | GET | `finance.refund/log` | 退款尝试日志 |
| `/adminapi/finance.refund/stat` | GET | 参考系统未登记 | 退款金额统计 |

LikeAdmin 对已登记但未授权的 URI 返回“权限不足，无法访问或操作”；Peanut 继续使用既有 `40300 / 暂无访问权限`。`finance.refund/stat` 的未登记放行是参考行为，实施时不得用它代替其他菜单或写接口的明确授权。

## 3. 充值记录列表

### 3.1 输入和校验

| 参数 | 规则 |
|---|---|
| `sn` | 精确匹配充值单号 |
| `user_info` | 模糊匹配用户 `sn/nickname/mobile/account` |
| `pay_way` | 精确匹配；1 余额、2 微信、3 支付宝 |
| `pay_status` | 精确匹配；0 未支付、1 已支付 |
| `start_time/end_time` | `YYYY-MM-DD HH:mm:ss`；两者同时存在才按下单时间闭区间筛选 |
| `page_no/page_size` | 默认 1/25；单次最多 25,000 条 |
| `page_type` | 默认 1 分页；0 表示不分页并最多返回 25,000 条 |
| `export` | 1 获取导出信息；2 生成 XLSX |

公共列表异常文案：

- `end_time <= start_time`：`搜索的时间范围不正确`；
- 超过最大条数：`已超出系统限制数量，请分页查询或导出，当前最多记录数为：25000`；
- 无数据导出：`没有数据,无法导出`；
- 分页范围无数据：`第X页到第Y页没有数据，无法导出`。

### 3.2 查询和响应

参考查询使用 `recharge_order ro INNER JOIN user u`，排除软删除充值单，按 `ro.id DESC`。用户物理不存在时关联充值单不显示。

列表行字段固定为：

```text
id, sn, order_amount, pay_way, pay_time, pay_status,
create_time, refund_status, avatar, nickname, account,
pay_status_text, pay_way_text
```

- `pay_time` 未支付时为空字符串，否则为格式化时间；
- `create_time` 为格式化时间；
- 只有 `pay_status=1` 的记录显示退款入口；
- `refund_status=1` 表示已发起退款，退款入口禁用。该字段不是最终渠道成功状态。


正常列表返回 `lists/count/page_no/page_size/extend`，其中 `extend=[]`。Peanut 只保留自身全局 envelope 差异，业务字段和口径与参考一致。

### 3.3 导出

`export=1` 返回：

```text
count, page_size, sum_page, max_page, all_max_size,
page_start, page_end, file_name
```

`export=2` 返回下载 URL。默认文件名为“充值记录”，列固定为：

```text
充值单号、用户昵称、充值金额、支付方式、支付状态、支付时间、下单时间
```

## 4. 增量问题：部分与多次退款（实现中，数据库资格阻塞）

> 本节是后续目标契约，不是 F02 已通过能力。2026-08-20 在登记的 `audit20b` 现场验证 `30.00` 首笔及同一幂等 key 重放后，`70.00` 第二笔未通过；预期的两条退款记录、两条余额流水和累计金额断言未成立。当前最可能的阻塞点是 `pa_member_balance_log` 仍以 `(tenant_id, source_sn)` 唯一，而每笔退款沿用充值订单号作为 `source_sn`。该归因仍需代码修复后按失败组重跑确认。

### 4.1 请求和参考校验

请求：

```json
{"recharge_id": 1, "refund_amount": "30.00"}
```

校验顺序与文案：

1. 缺少参数：`参数缺失`；
2. 充值订单不存在：`充值订单不存在`；
3. `pay_status != 1`：`当前订单不可退款`；
4. 退款金额缺失时默认使用当前可退款余额；指定金额必须大于 0 且不超过当前可退款金额；
5. 用户余额不足：`退款失败:用户余额已不足退款金额`。

同一充值单可按多笔退款记录累计退款；省略 `refund_amount` 时执行剩余金额的全额退款。

### 4.2 Peanut 原子业务阶段

Peanut 在一个数据库事务中完成以下步骤，并对充值单和会员余额行加排他锁：

1. 在订单行锁内重新校验订单存在且已支付；
2. 在同一订单锁内汇总历史退款，计算本次可退款余额并校验可用余额；
3. 将充值单 `refund_status` 从 0 原子更新为 1；
4. 用户 `user_money` 和 `total_recharge_amount` 各扣减一次本次退款金额；
5. 写一条 `change_type=101, action=2` 的充值退款余额流水；
6. 创建一条独立的 `refund_record`，初始状态为 0；
7. 创建首次 `refund_log`，初始状态为 0；
8. 提交本地事务后调用支付渠道。

余额流水至少满足：

```text
change_object = 1
change_type = 101
action = 2
change_amount = 本次 refund_amount
left_amount = 扣减后的 user_money
source_sn = recharge_order.sn
remark = 充值订单退款
```

退款额度由订单行锁与累计金额校验共同保证；每笔退款记录、余额流水和渠道日志独立保存。相同请求使用 `Idempotency-Key` 防止重复创建，不能仅依靠请求前校验。

### 4.3 目标退款状态

```text
充值单 refund_status：0 未发起 → 1 已发起

refund_record：
  0 退款中 ──渠道确认成功──> 1 退款成功
  0 退款中 ──明确请求失败──> 2 退款失败

refund_log：每次渠道尝试独立记录 0/1/2
```

渠道请求明确失败时，Peanut 保留已提交的一次性扣款、`refund_record` 和失败 `refund_log`，记录可重试错误原因；不能因异常回滚到“没有退款记录但渠道状态未知”的状态。

## 5. 失败重试

请求：

```json
{"record_id": 1}
```

基础文案：

- 缺少参数：`参数缺失`；
- 退款记录不存在：`退款记录不存在`；
- 已退款成功：`该退款记录已退款成功`。

Peanut 的安全重试规则：

- 只允许状态 2 的记录重试；状态 0 不允许并发重试，状态 1 永不重试；
- 锁定 `refund_record` 后重新检查状态；
- 复用同一条 `refund_record`，每次尝试新增唯一 `refund_log`；
- 重试开始时将主记录恢复为 0，并清除或更新本次展示错误；
- 只调用支付渠道，不再次扣减 `user_money`、`total_recharge_amount`，不新增余额流水，不新建退款记录；
- 不再次以当前用户余额作为重试门槛，因为首次退款已经完成一次性扣款；
- 同一时刻最多存在一个有效的退款中尝试。

重试失败回到状态 2，可继续重试；只有真实渠道确认成功才能进入状态 1。

## 6. 支付渠道结果

### 6.1 微信

参考请求：

```text
POST v3/refund/domestic/refunds
transaction_id = recharge_order.transaction_id
out_refund_no = refund_log.sn
amount.refund = refund_amount * 100
amount.total = order_amount * 100
currency = CNY
```

微信受理请求不等于退款成功，记录保持 0。后续使用退款日志单号查询：

```text
GET v3/refund/domestic/refunds/{refund_log.sn}
```

只有渠道明确返回成功，才将当前日志和主记录更新为 1。明确失败更新为 2 并保留原因；未知、处理中结果继续保持 0。回调或轮询更新必须校验当前记录和尝试日志，并保持幂等。

### 6.2 支付宝

参考调用：

```text
refund(order_sn, refund_amount, refund_log.sn)
```

只有同时满足以下真实响应条件才同步标记成功：

```text
code == '10000'
msg == 'Success'
fundChange == 'Y'
```

成功时更新当前日志、主记录及 `recharge_order.refund_transaction_id`。非成功响应、渠道未配置、SDK 异常或网络异常均不得伪造成成功；应保留失败或处理中状态及可诊断原因。

### 6.3 禁止伪造成功

开发和验收环境没有真实渠道成功凭据时，只能验证明确失败、保留记录、幂等和重试规则。不得通过硬编码响应、空实现、直接改成功状态或吞掉异常来制造“退款成功”。真实成功状态只能来自签名可信的支付回调、渠道查询结果或同步成功响应。

Peanut 在支付网关 `refund/query` 公共出口统一校验成功结果：若渠道状态为成功但退款交易号为空，则抛出“结果未知”，首次请求和对账命令都保持退款中，禁止写入“成功但无交易号”的状态。

## 7. 退款记录、日志与统计

### 7.1 退款记录

`GET /adminapi/finance.refund/record` 支持：

- `sn/order_sn/refund_type` 精确匹配；
- `user_info` 模糊匹配用户 `sn/nickname/mobile/account`；
- `refund_status` 精确匹配 0/1/2；
- `start_time` 独立 `>=`、`end_time` 独立 `<=`；
- 服务端分页；`export=1/2` 返回 `该列表不支持导出`。

列表按 ID 倒序，行字段为 `refund_record.*` 加：

```text
nickname, avatar, refund_type_text, refund_status_text, refund_way_text
```

`extend` 返回忽略当前 `refund_status`、保留其他筛选后的记录数：

```json
{"total": 0, "ing": 0, "success": 0, "error": 0}
```

### 7.2 退款日志

`GET /adminapi/finance.refund/log?record_id=1` 返回该记录的全部尝试，最新在前，不分页。字段为：

```text
id, sn, record_id, user_id, handle_id,
order_amount, refund_amount, refund_status,
create_time, update_time, handler, refund_status_text
```

渠道错误详情不直接暴露给列表页面，但必须保存在后端供诊断。

### 7.3 退款金额统计

`GET /adminapi/finance.refund/stat` 返回：

```json
{"total": 0, "ing": 0, "success": 0, "error": 0}
```

Peanut 以 `refund_amount` 作为金额统计口径；全额充值退款时与参考的 `order_amount` 相等。

## 8. 数据模型和安全约束

### 8.1 充值单

`pa_recharge_order` 至少覆盖：

```text
id, sn, user_id, pay_sn, pay_way, pay_status, pay_time,
order_amount, order_terminal, transaction_id,
refund_status, refund_transaction_id,
create_time, update_time, delete_time
```

要求：`sn` 唯一；用户、支付状态、退款状态和时间筛选具备必要索引；模型使用软删除。

### 8.2 退款记录

`pa_refund_record` 至少覆盖：

```text
id, sn, user_id, order_id, order_sn, order_type,
order_amount, refund_amount, transaction_id,
refund_way, refund_type, refund_status, refund_msg,
create_time, update_time
```

要求：

- `sn` 唯一；
- `(tenant_id, order_type, order_id, refund_amount, id)` 组合索引支持累计退款查询；同一充值单允许多条退款记录；
- 用户、状态和创建时间具备查询索引；
- `refund_msg` 存放最近一次可诊断错误。

### 8.3 退款日志

`pa_refund_log` 至少覆盖：

```text
id, sn, record_id, user_id, handle_id,
order_amount, refund_amount, refund_status, refund_msg,
create_time, update_time
```

要求：`sn` 唯一；`record_id` 和 `refund_status` 具备查询索引；每次尝试只新增一条日志，渠道结果更新同一条日志。

## 9. 已确认的 LikeAdmin 参考缺陷与 Peanut 决策

| 参考现状 | 风险 | Peanut 决策 |
|---|---|---|
| 首次退款流水使用 `change_type=200` 配合扣减 action | 流水文案与业务方向错误 | 使用语义正确的 `change_type=101, action=2` |
| 资格校验在事务外，事务内无行锁和条件更新 | 并发重复扣款、重复退款 | 事务内锁单和锁余额，条件更新并增加唯一约束 |
| 充值单、退款单和退款日志单号无唯一索引 | 并发编号冲突 | 三类业务单号均建立唯一索引 |
| 渠道调用发生在本地事务提交前 | 渠道已受理而本地回滚 | 先持久化一次性业务状态，再调用渠道并落尝试结果 |
| 失败处理写入安装 SQL 不存在的 `refund_record.refund_msg` | 失败路径再次异常并可能整体回滚 | 模型明确提供 `refund_msg`，失败记录必须保留且可重试 |
| 首次渠道失败仍已扣款，但重试再次校验当前余额 | 正常失败记录可能无法重试 | 重试不校验当前余额，也不重复扣款 |
| 后端允许状态 0 直接重试 | 多个渠道请求并发 | 只允许状态 2 重试，并锁定主记录 |
| 重试新增日志但不把主记录恢复为 0 | 页面仍显示失败，状态不一致 | 重试开始时主记录和新日志统一为 0 |
| 支付宝非成功响应可能仍被当成请求成功，轮询又无支付宝分支 | 永久退款中或假成功 | 非成功不得标成功；同步结果明确分类并保留原因 |
| 微信只处理成功，不归档明确失败终态 | 永久退款中 | 明确失败转 2，未知或处理中才保持 0 |
| 退款统计使用 `order_amount` | 部分退款时金额口径错误 | 使用 `refund_amount` |
| 充值支付回调无状态幂等和行锁 | 重复增加余额和累计充值 | 支付成功回调按订单锁和支付状态幂等处理 |

这些差异属于安全性和可用性修复。首次全额退款基线已验收；部分/多次退款的金额与次数契约仍待数据库资格通过，失败重试和真实渠道确认规则沿用本节目标设计。

## 10. 验收矩阵

2026-08-01 已完成代码、迁移、双系统真实浏览器、API、权限、数据库不变量和隔离夹具清理。每项只执行一次最低充分验收；历史失败探针仅作为诊断记录，不替代下表最终结论。

| 编号 | 验收项 | 最低充分证据 | 状态 |
|---|---|---|---|
| F02-01 | 充值列表字段、默认排序、用户 inner join 与软删除 | 两端同夹具列表字段逐项对比 | 通过 |
| F02-02 | sn、四种 user_info、pay_way、pay_status 和时间闭区间 | 两端真实浏览器网络请求与命中数 | 通过 |
| F02-03 | 分页、`page_type=0` 和 25,000 上限 | 两端各一次页码切换与 API 响应 | 通过 |
| F02-04 | `export=1/2` 信息、XLSX 列和内容 | 两端实际下载并做一次 OpenXML 完整性检查 | 通过；金额均为数值单元格 |
| F02-05 | 首次退款资格异常文案 | 不存在、未支付、已发起、余额不足各一次 | 通过 |
| F02-06 | 首次退款一次性联动 | 余额与累计充值各扣一次、101 流水一条、record 一条、log 一条 | 通过 |
| F02-07 | 首次退款并发幂等 | 同一订单并发请求后仍只扣一次且主记录唯一 | 通过 |
| F02-08 | 渠道明确失败保留记录 | record/log 为失败并保留原因，余额不重复变化 | 通过 |
| F02-09 | 失败重试 | 同一 record 新增一条 log，不新增 record/流水，不再扣余额或累计充值 | 通过 |
| F02-10 | 重试并发和状态限制 | 退款中/成功禁止重试，失败记录同刻只有一个有效尝试 | 通过；4 worker 并发验收 |
| F02-11 | 微信异步或轮询状态 | 仅真实渠道确认成功转 1，明确失败转 2，处理中保持 0 | 通过 |
| F02-12 | 支付宝同步状态 | 仅真实成功三条件转 1，并保存退款交易号 | 通过；无真实凭据时未伪造成功 |
| F02-13 | 退款记录筛选、tabs 计数、统计和日志倒序 | 两端同夹具 API 与真实页面对比 | 通过；含单边时间和禁止导出 |
| F02-14 | 普通角色菜单、按钮与 API 权限 | 授权/撤权各一次，拒绝请求前后数据不变 | 通过；LikeAdmin 使用真实 `runtime/adminapi` 缓存 |
| F02-15 | 异常路径事务和无假成功 | 渠道未配置/异常时无成功状态、无重复扣款、无悬挂事务 | 通过；数据库断言 6/6 |
| F02-16 | 清理 | 两端夹具、临时角色、管理员、退款记录、日志和流水精确核验为 0 | 通过；两端联合计数均为 0 |

核心证据位于：

- `output/playwright/f02/recharge-matrix/api-matrix.json`；
- `output/playwright/f02/refund-matrix/browser-matrix.json`；
- `output/playwright/f02/peanut-permission/README.md`；
- `output/playwright/f02/likeadmin-permission-correct/README.md`；
- `output/playwright/f02/remaining-api/README.md`；
- `output/playwright/f02/f02-15-audit.json`；
- `output/playwright/f02/f02-16-cleanup.json`；
- `output/playwright/f02/cleanup-likeadmin/README.md`。

## 11. 参考调用链

```text
RechargeController::lists
  → RechargeLists

RechargeController::refund
  → RechargeRefundValidate::checkRecharge
  → RechargeLogic::refund
  → AccountLogLogic::add
  → RefundRecord::create
  → RefundLogic::refund
  → RefundLog::create
  → WeChatPayService::refund / AliPayService::refund

RechargeController::refundAgain
  → RechargeRefundValidate::checkRecord
  → RechargeLogic::refundAgain
  → RefundLogic::refund

RefundController::record/log/stat
  → RefundRecordLists / RefundLogic::refundLog / RefundLogic::stat

query_refund
  → QueryRefund::checkReFundStatus
  → WeChatPayService::queryRefund
  → RefundLog + RefundRecord 状态更新
```

关键参考文件位于：

- `server/app/adminapi/controller/recharge/RechargeController.php`；
- `server/app/adminapi/validate/recharge/RechargeRefundValidate.php`；
- `server/app/adminapi/logic/recharge/RechargeLogic.php`；
- `server/app/adminapi/lists/recharge/RechargeLists.php`；
- `server/app/Modules/Official/Payment/Http/Controller/RefundController.php`；
- `server/app/adminapi/lists/finance/RefundRecordLists.php`；
- `server/app/common/logic/RefundLogic.php`；
- `server/app/common/command/QueryRefund.php`；
- `server/public/install/db/like.sql`。
