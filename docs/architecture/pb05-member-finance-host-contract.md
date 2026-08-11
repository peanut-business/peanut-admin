# PB05 会员与财务 Host 合同

> 状态：Accepted
>
> 应用前置提交：`c0ede32be9c0ebf7b8fce5ced52cb828cca76f96`
>
> 核心只读基线：`7fbd445d8fa547830b7782a7ac147d9ed414e0fd`
>
> 应用测试 owner：`PB05-MEMBER-FINANCE-001`

## 1. 决策与唯一所有权

会员与财务是 Peanut Admin 产品领域，继续由应用 Module 唯一拥有，不迁入核心：

- 应用拥有客户会员、资料/状态/标签、`pa_member`、余额与流水、充值订单、退款记录/日志、管理与会员 HTTP/UI，以及支付回调后的产品结算状态机。
- `MemberBalanceService` 是 `user_money`、兼容 `balance`、累计充值和分类流水的唯一写入入口；后台余额调整、可信支付回调入账和首次充值退款只装配各自领域状态。
- 管理端 `RechargeLogic` 拥有退款，会员端 `RechargeLogic` 拥有建单/预支付/可信回调结算；两者职责与状态不同，不是同一用例的双实现。
- 核心 Tenant membership 是管理账号与 Tenant 的成员关系，不是客户会员。核心 R01/R02 事务、幂等、审计和 Host kit 只是未来候选，不提供余额、流水、充值或退款模型。
- Alpha.2 发布不批准 Peanut Admin 迁移，R01/R02 也没有固定候选聚合资格与下游采用授权。本片不 deep import、不新增 override、不复制核心 Runtime、不双写核心 schema。

## 2. 数据与金额合同

| 数据 | 权威语义 | 兼容/派生语义 |
|---|---|---|
| `pa_member.user_money` | 当前可用余额，所有扣减不得小于 0 | `balance` 只为旧调用方兼容镜像，任何成功变动必须同事务同步 |
| `pa_member.total_recharge_amount` | 成功充值累计额；首次充值退款同步扣回 | 不是当前余额，不受后台普通调账影响 |
| `pa_member_balance_log` | 每次成功余额变动一条分类流水，`left_amount` 是变动后余额 | `after_amount` 只为旧字段镜像 |
| `pa_recharge_order` | 充值订单与 unpaid→paid 状态 | `transaction_id` 唯一，重复可信回调不得再次入账 |
| `pa_refund_record/log` | 每个来源订单唯一主退款记录及逐次渠道尝试 | 失败重试只追加尝试，不再扣余额或累计充值 |

金额在写入链中先转换为整数分，完成加减和非负校验后再格式化为两位小数。数据库仍以现有 `DECIMAL(10,2)` 保存；本片不增加币种、多余额账户、冻结余额、积分或跨币种结算。

## 3. 唯一事务写入链

```text
后台调账 ─┐
可信充值回调 ─┼─> caller domain transaction
首次充值退款 ─┘       └─> MemberBalanceService(row lock)
                              ├─> user_money + balance
                              ├─> total_recharge_amount(optional)
                              └─> AccountLogLogic -> pa_member_balance_log
```

固定规则：

1. 三个调用方先开启包含自身订单/退款状态的 ThinkPHP 数据库事务，再调用 `MemberBalanceService::applyInTransaction`；服务锁定会员行并同步余额、镜像、可选累计充值和流水。任一步失败由调用方整体回滚。
2. `AccountLogLogic` 只创建已经完成余额变动后的流水，不允许 controller、支付适配器或其他领域绕过余额服务直接调用。
3. `user_money` 是应用源码内唯一权威 writer marker；旧 `balance` 不能反向覆盖它。迁移只在首次新增 `user_money` 时从旧字段初始化。
4. 后台调账每次成功请求都是独立业务操作，并记录管理员与备注；当前没有请求级幂等 key，UI/调用方在超时后必须先查流水再决定是否重试。
5. 余额验证器只能提前改善错误提示，真正的非负检查必须在会员行锁内再次执行。

## 4. 充值与退款防重

- 回调 Parser 先验签并标准化渠道事件；结算再次校验成功状态、CNY、订单号、金额、支付方式和交易号。
- 结算事务先锁充值订单。已支付且交易号相同返回成功但不调用余额服务；交易号不同拒绝。不同订单复用交易号由应用冲突查询与数据库 `uk_transaction_id` 共同拒绝。
- 首次退款锁充值订单并确认已支付/未发起退款，再检查 `(order_type, order_id)` 唯一主记录；余额扣减、101 流水、订单 refund 状态和主退款记录同事务提交。
- 渠道退款只在本地事务提交后调用。明确失败保留 ERROR；结果未知保持 ING 交给对账。`refundAgain` 只允许 ERROR，复用主记录、用 MySQL 命名锁覆盖渠道调用周期，且不调用余额服务。
- 本片不改变支付渠道工厂、回调验签、渠道凭据、对账命令或 PB07 的支付/OAuth/渠道所有权。

## 5. 会员、标签、查询与权限边界

- 会员资料、状态、标签和标签关联继续由应用 `MemberLogic`/`MemberTagLogic` 及现有表唯一拥有；它们不写余额。
- 管理端读取会员、余额流水、充值和退款；PC/UniApp 当前只消费本人充值接口。缺少端入口不是核心包迁移理由。
- 管理接口继续经过 `LoginMiddleware -> AuthMiddleware -> OperationLogMiddleware`；会员接口继续使用会员 token，支付回调保持匿名路由但必须通过渠道验签。
- 本片复用已经封存的 LikeAdmin 页面/API/权限结果，不重复 PB00、F02、S01 或浏览器验收。

## 6. 精确写集与禁改集

Runtime 白名单：

- `server/app/common/service/MemberBalanceService.php`；
- `server/app/adminapi/logic/member/MemberLogic.php`；
- `server/app/api/logic/RechargeLogic.php`；
- `server/app/adminapi/logic/finance/RechargeLogic.php`。

证据与状态白名单：

- `server/tests/Productization/MemberFinanceHostTest.php`；
- `.github/workflows/ci.yml`，只登记无数据库聚焦测试；
- 本合同、`docs/architecture/pb03-ownership-and-migration-gates.md`、`docs/architecture/core-application-capability-graph.md`、`docs/architecture/application-package-and-release-contract.md`、`docs/productization-baseline-plan.md`、`AGENTS.md`；
- 开发指南、用户手册及其 `docs-site` 镜像，只同步唯一余额写入与防重边界。

禁止修改核心仓、`vendor/`、`node_modules/`、schema/seed、路由/菜单、会员/财务页面、支付/OAuth/通知、PB08A 品牌输入或 SaaS 设计。

## 7. 测试 owner 与一次最低验收

`PB05-MEMBER-FINANCE-001` 由 `server/tests/Productization/MemberFinanceHostTest.php` 拥有且不连接数据库、不写文件。一次运行证明：

1. 整数分转换稳定，应用 `user_money` 只有 `MemberBalanceService` 一个 writer；三条变动路径均在领域事务内调用它，不再直接写流水。
2. 服务锁会员并在保存新余额后追加流水；充值累计额和兼容余额不再由各调用方重复实现。
3. 支付订单行锁和 paid guard 位于入账前，`uk_transaction_id` 存在；退款重试不扣第二次，`uk_order_type_order_id` 存在。
4. 只读绑定封存 S01 的单次入账/重复回调/单流水证据和 F02 的单退款记录/单次 101 扣款/余额一致性证据。
5. 应用余额 owner 不 deep import 核心 Runtime。

执行命令固定为：

```bash
cd server
/opt/homebrew/opt/php@8.3/bin/php tests/Productization/MemberFinanceHostTest.php
```

实现 owner 另运行一次白名单 PHP lint 和一次最终 `git diff --check`。不执行数据库写入、支付/退款渠道调用、封存 F02/S01、LikeAdmin parity、全量 API/Web、核心候选或浏览器。

## 8. 停止线

通过只表示应用会员/财务产品 owner、唯一余额写入链、事务/防重边界和测试 owner 已固定。它不批准核心 R01/R02 或 Tenant membership 消费，不实现新支付渠道、订单系统、多币种/冻结资金/会计总账，不开始 PB06、PB07 或 SaaS。

## 9. 实施证据

- CodeGraph 限定图谱与两组只读审计确认应用只有后台调账、可信充值回调和首次充值退款三条余额变动链；核心 Tenant membership、R01/R02 不含客户财务模型且没有下游采用授权。
- PHP 8.3 下四个 Runtime 文件和新增测试的一次白名单 lint 全部通过。
- 首次测试命令因测试源码字符串未转义 `$orderSn` 产生 warning，使订单行锁子断言无效；仅机械修复该测试后，按失败组预算重跑一次，`PB05-MEMBER-FINANCE-001` 无 warning 通过。
- 有效验收证明 `user_money` 只有 `MemberBalanceService` 一个 writer，三条调用链均保留外层领域事务，余额/镜像/累计充值/流水不再由调用方重复实现。
- 同次验收证明订单行锁与 paid guard 先于充值入账、交易号和单退款唯一键存在、`refundAgain` 不再次扣款，并只读绑定封存 S01/F02 的单次入账与退款一致性证据。
- 测试没有连接数据库、写文件或调用支付/退款渠道；未执行 S01/F02、LikeAdmin parity、全量 API/Web、核心候选或浏览器。
- 核心仓和既有 `.playwright-cli/` 未触碰；应用 schema/seed、路由/菜单、前端页面、PB08A 品牌输入及 SaaS 设计未修改。
