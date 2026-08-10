# Peanut Admin 核心包、发布保护与生产候选架构 Claude 审核包

> 状态：`review-required / no-code-authorized`
> 日期：2026-08-05
> 审核对象：核心包边界、独立发布、源码暴露策略、前端 UI 统一、无外部凭据生产加固
> 当前停止线：审核完成并经用户确认前，不修改业务代码、依赖、数据库、运行环境、Git remote、远程仓库可见性或历史。

## 1. 给 Claude 的任务

请作为独立的架构、安全、开源治理和发布工程审核者，对本文方案做对抗式审核。不要编写代码，不要修改仓库，不要把“可以实现”当作“设计已经正确”。

审核必须回答：

1. 核心能力是否被放入正确、可独立安装的 PHP/npm 包，是否仍有 Host 代码反向污染核心包；
2. 包数量和边界是否合理，是否出现为了目录整齐而过度拆包；
3. 前后端 Package、Module、Host、Client 的职责是否清晰且能被真实下游消费；
4. 独立发布、共享版本、兼容矩阵和升级机制是否可长期维护；
5. 发布时提高阅读成本的措施是否与开源、许可证、调试、供应链安全和客户运维相容；
6. Element Plus 单 UI 路线是否是当前证据下风险最低的选择；
7. 不依赖外部账号的生产加固清单是否足以形成 `production candidate`；
8. 哪些结论必须阻塞编码，哪些可以作为后续非阻塞改进。

## 2. 已确认的用户方向

以下方向已经确认，不需要重新争论目标，但可以指出实现风险：

1. 暂不处理业务代码，先审核架构与执行边界；
2. 尽可能把跨项目可复用的核心功能和可用能力放入库包，应用只保留装配和产品差异；
3. PHP 与 Web 核心包必须可以独立构建、安装和发布；
4. 发布产物应在不破坏运行、调试和许可证边界的前提下增加阅读成本；
5. 必要时允许代码仓和 Package Registry 由公开调整为私有；仓库可见性不是架构耦合条件；
6. 当前不修改任何远程仓库；
7. 需要外部账号、真实商户、真实域名或第三方凭据的生产验收暂缓；其余生产加固在审核通过后实施；
8. 管理端只保留一套 UI 体系，采用证据更成熟的一套。

## 3. 当前事实

### 3.1 当前产品代码仓

当前工作目录采用以下应用式结构：

```text
server/    ThinkPHP 8 后端 Host
web/       Arco 管理端应用
pc/        Nuxt 3 PC 用户端 Client
uniapp/    H5/小程序/App Client
```

当前仓没有 `packages/` 目录。`pc/package.json` 标记为 `private: true`，因此 `peanut-admin-pc` 是应用标识，不是 npm 核心包。

### 3.2 已有核心包实现线

已有 Package 架构使用：

```text
backend/                 参考后端 Host
frontend/                Element Plus 管理端 Host
frontend-arco/           后加入的 Arco 管理端候选
packages/php/*           Composer 核心与功能包
packages/web/*           npm 核心与功能包
starter/                 固定下游消费示例
```

已核对的前端证据：

| 项目 | `frontend/` | `frontend-arco/` |
|---|---:|---:|
| UI | Element Plus | Arco Design |
| 集成历史 | 至少 8 轮提交 | 1 次模板导入 |
| 测试文件 | 13 | 0 |
| 核心包消费 | 已消费 `admin-core`、`admin-shell` 和功能包 | 未消费核心包 |
| 工具链 | Vue 3.5 / Vite 8 | Vue 3.2 / Vite 3 |
| 应用身份 | `@peanut-admin/reference-admin` | 仍为上游模板身份 |

当前建议是统一 Element Plus；现有 Arco 业务页面在建立行为映射并迁移完成前不得删除。

### 3.3 当前测试资格

当前产品已有空库安装、数据库/API、浏览器和业务不变量验收，但外部能力仍有明确边界：

- 支付使用注入传输验收，未调用真实商户；
- 微信身份能力使用注入传输验收，未调用真实微信账号；
- 公众号菜单未真实发布，回调当前只具备明文模式；
- 短信/邮件未验证真实送达；
- 云存储未用真实七牛、OSS、COS 账号完成全生命周期验收。

因此当前只能作为功能候选，不得宣称外部集成已达到生产可用。

## 4. 建议的总体分层

```text
Product / Client
  ├── frontend             管理端产品装配、最终路由、主题和产品页面
  ├── pc                   PC 用户端
  ├── uniapp               H5/小程序/App
  └── backend              HTTP/CLI/Job Host 与项目装配

Reusable Packages
  ├── packages/php         Kernel、数据权限、测试和可复用功能包
  └── packages/web         Admin Core、Shell、测试和功能 UI 包

Product Modules
  └── 仅保留不能成为通用包的行业业务、客户定制和产品流程
```

Host 只能通过 Package public API 使用能力，禁止 deep import 包内部实现。Package 不得依赖具体产品路由、主题、客户配置、部署密钥或演示数据。

## 5. 后端包建议

### 5.1 必要核心包

| Composer 包 | 职责 |
|---|---|
| `peanut-admin/kernel` | 可信上下文、身份、Tenant/TenantMember、Session、平台/租户授权、审计、Module Runtime、迁移与健康契约 |
| `peanut-admin/data-permission` | 查询谓词、单对象动作授权、Target 类型和数据范围契约 |
| `peanut-admin/testing` | 跨租户、授权、迁移、Module 和 Host 消费测试辅助 |

审核重点：`auth`、`tenancy`、`membership` 是否应继续作为 `kernel` 内部边界，而不是过早拆成更多包。

### 5.2 可复用功能包候选

| 能力 | 建议 PHP 包 | 边界要求 |
|---|---|---|
| 配置 | `peanut-admin/settings` | 类型化定义、作用域、加密值、审计；不包含产品页面 |
| 文件与存储 | `peanut-admin/file-media` | 元数据、Provider 契约、本地适配器；云 Provider 可独立 |
| 字典/参考码 | `peanut-admin/reference-codes` | 稳定码表、层级和占用约束 |
| 任务调度 | `peanut-admin/task-job` | 注册、认领、重试、执行记录；不包含业务命令 |
| 通知 | `peanut-admin/notification` | 场景、模板、发送状态和 Provider 契约 |
| 导入导出 | `peanut-admin/import-export` | 作业、格式、限制、临时文件和审计 |
| 集成安全 | `peanut-admin/integration-security` | 签名、重放防护、幂等和外部调用安全原语 |
| 运维控制 | `peanut-admin/ops-console` | 健康、日志、维护动作的受控 public API |
| 支付 | `peanut-admin/payment` | Gateway/Callback 契约、标准事件、验签、幂等与结算原语；订单归属仍由 Host/业务 Module 决定 |
| 身份 Provider | `peanut-admin/oauth-provider` | OAuth state、身份归一和 Provider 契约；Account/TenantMember 仍由 Kernel 拥有 |
| 微信渠道 | `peanut-admin/channel-wechat` | 公众号、小程序、开放平台适配；不得成为 Kernel 依赖 |

请重点审核 `payment`、`oauth-provider`、`channel-wechat` 是否应该继续拆为契约包与 Provider 包，以及通知、短信是否应分包。

### 5.3 不进入核心包

- 商品、库存、门店、仓库等行业业务；
- 具体产品控制器和最终页面路由；
- 客户定制、品牌主题、部署密钥和生产账号；
- 只为当前页面服务、没有稳定 API 的临时 DTO；
- 直接修改会员余额、订单或其他 Host 所有数据的跨域捷径。

## 6. 前端包建议

### 6.1 必要核心包

| npm 包 | 职责 |
|---|---|
| `@peanut-admin/admin-core` | API Client、登录状态、租户切换、权限判断、公共错误和稳定类型；保持 UI 无关 |
| `@peanut-admin/admin-shell` | Element Plus 布局、菜单、路由容器和 Module 扩展槽 |
| `@peanut-admin/testing` | Store、权限、路由、组件和 Host 消费测试辅助 |

### 6.2 功能 UI 包

稳定的后端功能包可以有同名 Web contribution，例如：

```text
@peanut-admin/settings
@peanut-admin/file-media
@peanut-admin/reference-codes
@peanut-admin/task-job
@peanut-admin/notification
@peanut-admin/import-export
@peanut-admin/integration-security
@peanut-admin/ops-console
@peanut-admin/payment
@peanut-admin/channel-wechat
```

Web 包只提供可复用页面、组件、路由声明、菜单声明和类型化 API；最终导航、主题、品牌和产品组合仍由 `frontend/` 决定。

## 7. 抽包判定规则

能力满足下列大部分条件时默认抽为 Package：

1. 对至少两个 Client、Product 或外部 Host 有明确复用价值；
2. public API、错误、权限和数据所有权可以稳定描述；
3. 可以独立测试、安装、升级和禁用，不需要 deep import Host 内部实现；
4. 拥有自己的迁移、配置、权限和资源生命周期；
5. 不包含客户、行业或部署专属信息；
6. PHP 与 Web contribution 可以独立存在，缺少 Web 包时后端仍可工作；
7. 删除 Host 中的复制实现后，至少一个真实下游仍能通过标准包消费。

若能力尚无第二消费者，但属于明确的跨项目基础设施，可以先建立内部 Package；必须同时冻结 public API 和 Host 消费测试，不能仅为目录整齐拆包。

## 8. 包名和独立发布

### 8.1 命名空间

```text
Composer vendor:  peanut-admin/*
PHP namespace:    PeanutAdmin\*
npm scope:        @peanut-admin/*
```

不建议使用 `@peanut/*`：名称过泛，所有权和产品对应关系不清晰。

### 8.2 独立发布定义

每个包必须：

- 有独立 manifest、public API、依赖、测试和 changelog；
- 能单独生成 Composer/npm 制品；
- 能被一个仓库外的空白消费者安装；
- 不依赖 monorepo 相对源码路径才能运行；
- 在 release manifest 中记录精确版本和兼容范围。

v0.x 阶段建议所有 PHP/Web 包共享版本号，但制品分别发布、分别安装。等 public API 稳定且存在多个真实消费者后，再审核是否允许不同发布节奏。

## 9. 发布时提高阅读成本

### 9.1 开源核心包

开源核心不应以混淆冒充安全。源码公开时，重点是许可证、边界、审计和供应链完整性。

Web 包可以只发布：

```text
dist/*.js
dist/*.css
dist/*.d.ts
package.json
LICENSE
NOTICE
README.md
```

默认不发布 `src/`、测试、内部设计和 source map；构建可压缩并 mangling 内部局部名称，但 public export 必须稳定。

PHP 开源包按 PSR-4 发布源码，不使用 ionCube 或 SourceGuardian。

### 9.2 私有或商业包

优先使用：

1. 私有 Git 仓和私有 Composer/npm Registry；
2. 最小权限 Token、下载审计、撤销和版本锁定；
3. Web 仅发布无 source map 的构建产物；
4. 真正需要保密的算法只放服务端；
5. 离线交付的 PHP 商业模块才单独评估 ionCube/SourceGuardian，并要求 Loader、PHP 版本矩阵、故障诊断和应急解码流程。

仓库公开或私有不得改变 Package public API、测试和升级契约。

## 10. 前端统一方案

建议采用：

```text
frontend/          唯一管理端 Host，Element Plus
admin-core         UI 无关
admin-shell        Element Plus
frontend-arco/     迁移完成后删除
```

执行停止线：

1. 先建立 Arco 页面、API、权限、路由和用户结果映射；
2. 每个模块迁入 Element Plus 后执行一次最低充分行为验收；
3. 全量菜单、权限和关键业务均通过后才允许删除 Arco；
4. 迁移期间禁止两套 UI 同时新增同一功能；
5. 不把 UI 组件类型泄漏进 `admin-core`。

## 11. 无外部凭据生产加固范围

审核通过后允许实施：

### 11.1 CI 与质量门禁

- PHP lint、静态分析、单元/集成测试；
- TypeScript typecheck、lint、Vitest、生产构建；
- 空库安装、全部迁移和 schema 不变量；
- Playwright 核心业务回归；
- Composer/npm 依赖、许可证、密钥和包边界检查；
- `composer` path consumer、`npm pack` consumer 和 release dry-run。

### 11.2 安全与故障测试

- 登录限流、Session、权限默认拒绝；
- 上传 MIME、文件名、路径穿越和大小限制；
- SSRF、开放重定向、回调重放和幂等冲突；
- 敏感配置掩码、日志脱敏和错误信息；
- 支付/通知/渠道使用离线 fixture 和注入传输验证失败原子性；
- Cron 并发认领、重试、崩溃恢复和重复执行；
- 数据库备份、隔离恢复和迁移失败停止演练。

### 11.3 性能与运维

- 登录、菜单、列表、导出、支付回调的本地并发基线；
- 索引、慢查询、大分页和批量上限；
- 结构化日志、request ID、健康检查和错误分类；
- 发布 preflight、配置检查、回滚说明、SBOM、NOTICE 和 release manifest。

完成这些工作后只能标记为 `production candidate / external integration pending`。

## 12. 暂缓的外部验收

- 微信和支付宝真实商户预支付、通知、退款与对账；
- 公众号、小程序、开放平台真实账号和发布；
- 短信、邮件真实送达；
- 七牛、OSS、COS 真实账号全生命周期；
- 真实域名、备案、正式 HTTPS 证书、生产网络和真实监控平台。

这些项目未完成前不得宣称对应外部能力已经生产可用。

## 13. Claude 必须输出的格式

请严格输出以下结构：

```text
VERDICT: APPROVED | APPROVED_WITH_BLOCKERS | REJECTED

1. Blocking findings
   - [P0/P1] 问题、证据、风险、最低修正

2. Package boundary verdict
   - 必须保留、合并、拆分、删除或延后的包

3. Naming and release verdict
   - Composer/npm 前缀、独立制品、版本策略和 Registry 建议

4. Source exposure verdict
   - 开源、私有、dist-only、source map、PHP 编码的允许边界

5. Frontend verdict
   - Element Plus 统一是否成立；Arco 迁移和删除门禁

6. Production-candidate verdict
   - 无外部凭据任务是否完整；缺失的阻塞验收

7. Revised execution sequence
   - 按依赖顺序列出阶段、停止线和每阶段唯一最低充分验收

8. Explicit non-goals
   - 本轮禁止实施的事项
```

不得只回复“整体合理”或给出没有证据和优先级的泛化建议。

## 14. 审核事实源

审核前至少读取：

- `AGENTS.md`
- `README.md`
- `pc/package.json`
- `docs/peanut-admin-development-guide.md`
- `docs/design/saas-roadmap/rebuild-design/28-peanut-admin-candidate-baseline.md`
- `docs/design/saas-roadmap/rebuild-design/31-repository-and-package-boundaries.md`
- `docs/design/saas-roadmap/rebuild-design/32-decision-confirmation-list.md`
- `docs/design/saas-roadmap/rebuild-design/40-g04-module-runtime-contract.md`
- `docs/design/saas-roadmap/rebuild-design/44-g08-legacy-assets-license-disposition.md`
- `docs/design/saas-roadmap/rebuild-design/47-post-review-unified-calibration.md`
- `docs/design/saas-roadmap/rebuild-design/48-post-calibration-nine-role-review.md`
- `docs/design/saas-roadmap/rebuild-design/51-second-wave-recovery-and-runtime-remediation-decision.md`
- `docs/design/saas-roadmap/lifecycle/2026-07-25-lifecycle-architecture-v3.3.md`
- `output/playwright/s01/recharge-payment-summary.json`
- `output/playwright/s01/wechat-oauth-summary.json`
- `output/playwright/ch03/frontend-summary.json`

## 15. 最终停止线

在 Claude 给出完整审核结果且用户明确确认前：

- 不修改 PHP、Vue、TypeScript、SQL 或测试代码；
- 不安装、升级或移除依赖；
- 不启动服务、迁移数据库或连接生产系统；
- 不改 Git remote、仓库可见性、默认分支或远程历史；
- 不发布 Composer/npm 包；
- 不删除 `frontend-arco/` 或现有业务页面；
- 不把外部凭据缺失解释为已生产验收。
