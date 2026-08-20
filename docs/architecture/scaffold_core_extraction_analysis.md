# 脚手架代码 vs 核心库：提取分析报告

> 分析时间：2026-08-20
> 分析基线：scaffold `application-template-inventory.json` (1306 files)

---

## 核心结论

**你的直觉完全正确。** 当前脚手架中有大量代码本质上是"通用框架基础设施"，可以（也应该）被提取到核心库。目前它们以 `app-owned` 身份存在于脚手架模板中，意味着派生应用一旦修改了这些文件，后续升级就会产生冲突或被升级器跳过。

---

## 1. 当前现状数据

### 脚手架模板文件归属

| 归属类别 | 文件数 | 含义 |
|---------|-------|------|
| **managed** (框架管辖) | 298 | 升级器可以安全覆盖 |
| **app-owned** (业务管辖) | 697 | 升级器**不会碰**，由派生应用自行维护 |
| **generated-managed** | 13 | 元数据文件 |
| **总计** | 1306 | — |

### 核心库 vs 应用层代码量

| 层 | 文件数 | 代码行数 |
|----|-------|---------|
| **后端核心库** `peanut-admin/core` | 566 | ~50,000 |
| **后端应用层** `server/app/common/service` | 130 | ~10,000 |
| **后端应用层** `server/app/adminapi` | 124 | ~11,700 |
| **前端核心库** `@peanut-admin/admin` | ~70 | — |
| **前端应用层** `web/src/views` | 103 | ~18,000 |
| **前端应用层** `web/src/store+router+utils` | ~30 | ~1,500 |

### 后端核心库已经覆盖的领域（14 个子包）

`PeanutAdmin\Kernel` (Auth, Authorization, Cache, Context, Host, Http, Idempotency, Identity, Membership, Menu, Migration, Module, Organization, Override, Persistence, Platform, Tenancy) + `ArtifactRevision`, `Collaboration`, `DataPermission`, `EntitlementQuota`, `FileMedia`, `ImportExport`, `IntegrationSecurity`, `NotificationSms`, `OpsConsole`, `ReferenceCodes`, `Settings`, `TaskJob`, `Workflow`

### 前端核心库已经覆盖的领域（14 个子包）

`admin-core` (access, api, auth, generated, governance, lifecycle, module, runtime, targets) + `admin-shell` (config, layout, overrides, states, targets, theme) + `settings`, `reference-codes`, `file-media`, `task-job`, `notification-sms`, `import-export`, `ops-console`, `integration-security`, `client-core`, `client-nuxt`, `client-uniapp`, `testing`

---

## 2. app-owned 中的"不应该是 app-owned"清单

以下是 **server 485 个 app-owned 文件**的拆解：

| 目录 | 文件数 | 性质分析 |
|-----|-------|---------|
| `server/app/common` | **175** | ⚠️ **重灾区**：大量 service/model/enum/validate/contract 是通用基础设施 |
| `server/app/adminapi` | 124 | 混合区：controller + logic，部分是通用 CRUD，部分是业务定制 |
| `server/app/platform` | 59 | 租户管理 API，大部分应归核心 |
| `server/app/Modules` | 53 | 设计正确：模块化的业务代码，**不应该提取** |
| `server/app/api` | 35 | 消费端 API |
| `server/app/command` | 11 | CLI 命令 |

### 2.1 后端 `server/app/common/service` 的 29 个子目录分析

| 子目录 | 文件数 | 可提取到核心库？ | 分析 |
|--------|-------|---------------|------|
| `tenant/` | 11 | ✅ **应该提取** | TenantContext、TenantCache、TenantScope 等是纯框架基础设施，核心库 `Kernel\Tenancy` 已有对应位置 |
| `module/` | 3 | ✅ **应该提取** | ModuleExecutionContext/Guard 是框架强制合同，核心库 `Kernel\Module` 已有对应 |
| `permission/` | 1 | ✅ **应该提取** | 注册式权限策略是框架行为 |
| `platform/` | 1 | ✅ **应该提取** | 实例控制面策略属于框架 |
| `scaffold/` | ? | ✅ **应该提取** | 脚手架服务本身就是核心能力 |
| `diagnostics/` | ? | ✅ **应该提取** | 诊断工具是框架基础设施 |
| `instance/` | ? | ✅ **应该提取** | 实例管理是框架基础设施 |
| `audit/` | ? | ⚠️ 部分可提取 | 操作日志框架部分可提取，具体日志格式是业务 |
| `config/` | ? | ⚠️ 部分可提取 | 配置读写框架可提取，具体配置项是业务 |
| `dict/` | ? | ⚠️ 部分可提取 | 字典 CRUD 框架可提取，字典内容是业务 |
| `file/` | ? | ⚠️ 部分可提取 | 文件服务框架可提取（核心已有 `FileMedia`） |
| `crontab/` | ? | ⚠️ 部分可提取 | 定时任务框架可提取（核心已有 `TaskJob`） |
| `async/` | ? | ⚠️ 部分可提取 | 异步任务框架可提取 |
| `export/` | ? | ⚠️ 部分可提取 | 导入导出框架可提取（核心已有 `ImportExport`） |
| `notice/` | ? | ⚠️ 部分可提取 | 通知框架可提取（核心已有 `NotificationSms`） |
| `oauth/` | ? | ⚠️ 部分可提取 | OAuth 框架可提取（核心已有 `IntegrationSecurity`） |
| `payment/` | ? | ⚠️ 部分可提取 | 支付框架可提取 |
| `article/` | ❌ 不应提取 | 文章是 Peanut Admin 特有业务 |
| `decoration/` | ❌ 不应提取 | 装修是 Peanut Admin 特有业务 |
| `member/` | ❌ 不应提取 | 会员是 Peanut Admin 特有业务 |
| `finance/` | ❌ 不应提取 | 财务是 Peanut Admin 特有业务 |
| `hot_search/` | ❌ 不应提取 | 热搜是 Peanut Admin 特有业务 |
| `wechat/` | ❌ 不应提取 | 微信集成是特有业务 |
| `generator/` | ⚠️ 可能提取 | 代码生成器可能归核心 |
| `storage/` | ⚠️ 可能提取 | 对象存储适配器框架可提取 |
| `org/` | ⚠️ 可能提取 | 组织架构框架部分（核心已有 `Kernel\Organization`） |

### 2.2 前端应用层分析

| 目录 | 文件数 | 可提取到核心库？ |
|-----|-------|---------------|
| `web/src/store/modules/app/` | 3 | ✅ 应用状态管理大部分是框架行为（核心已有 `admin-shell/states`） |
| `web/src/store/modules/user/` | 2 | ✅ 用户认证状态管理是框架行为（核心已有 `admin-core/auth`） |
| `web/src/router/guard/` | 2 | ✅ 路由守卫是框架行为（核心已有 `admin-core/runtime/guard`） |
| `web/src/utils/auth.ts` | 1 | ✅ Token 管理是框架行为 |
| `web/src/layout/` | 2 | ⚠️ 布局壳子大部分是框架行为（核心已有 `admin-shell/layout`） |
| `web/src/views/` | 103 | ❌ 纯业务视图，不应提取 |
| `web/src/components/` | 8 个子目录 | ⚠️ 部分通用组件（navbar、menu、breadcrumb）可提取 |

---

## 3. 关键发现：核心库已有"对应位"但应用层仍有"平行实现"

这是最值得关注的问题。核心库的 Kernel 已经有 20 个子模块：

```
Auth, Authorization, Cache, Context, Host, Http, Idempotency, 
Identity, Membership, Menu, Migration, Module, Organization, 
Override, Persistence, Platform, Tenancy ...
```

但应用层的 `server/app/common/service/` 仍然有独立的：

```
tenant/      → 和 Kernel\Tenancy 平行
module/      → 和 Kernel\Module 平行
permission/  → 和 Kernel\Authorization 平行
platform/    → 和 Kernel\Platform 平行
audit/       → 和 Kernel\Audit 平行
org/         → 和 Kernel\Organization 平行
```

**这意味着核心库的合同定义和应用层的具体实现之间存在"桥接层"。** 这个桥接层目前以 `app-owned` 身份存在于脚手架中。

---

## 4. 影响评估：如果不提取会怎样？

### 升级场景模拟

假设核心库从 `0.1.0-alpha.5` 升级到 `0.2.0`，改动了 `Kernel\Tenancy` 的接口：

1. 核心库升级通过 `composer update` 完成 → **自动**
2. 但应用层 `server/app/common/service/tenant/TenantScope.php` 等 11 个文件是 `app-owned`
3. 如果派生应用**没改过**这些文件 → scaffold-upgrade 仍然不会碰它们（因为 app-owned）
4. 如果派生应用**改过**这些文件 → 更糟，手动合并

**结果：核心库升级了，但应用层的桥接代码没有跟着升级，产生运行时不兼容。**

### 如果提取到核心库呢？

1. 核心库同时包含合同和默认实现
2. 应用只通过 Override 机制注入定制行为
3. `composer update` / `pnpm update` 一步完成整个升级
4. 派生应用只要没有注册 override，就完全不受影响

---

## 5. 建议的提取优先级

### P0 — 立即提取（纯框架基础设施，零业务逻辑）

| 当前位置 | 目标位置 | 理由 |
|---------|---------|------|
| `app/common/service/tenant/*` (11 files, 637 lines) | `Kernel\Tenancy` | 纯框架 Tenant 上下文、缓存、作用域 |
| `app/common/service/module/*` (3 files, 282 lines) | `Kernel\Module` | 框架强制 Module Guard |
| `app/common/service/permission/*` (1 file, 42 lines) | `Kernel\Authorization` | 框架权限策略 |
| `app/common/service/platform/*` (1 file) | `Kernel\Platform` | 框架实例控制面 |
| `app/common/service/CoreServiceOverrides.php` (61 lines) | `Kernel\Override` | 框架覆盖注册中心 |
| `web/src/core/*` (5 files) | `@peanut-admin/admin/core` | 框架运行时桥接 |
| `web/src/router/guard/*` (2 files) | `@peanut-admin/admin/shell` | 框架路由守卫 |
| `web/src/store/modules/user/*` (2 files) | `@peanut-admin/admin/core` | 框架认证状态 |

### P1 — 短期提取（核心库已有对应子包）

| 当前位置 | 目标位置 | 理由 |
|---------|---------|------|
| `app/common/service/file/*` | `FileMedia` | 核心已有但应用层仍有平行实现 |
| `app/common/service/crontab/*` | `TaskJob` | 同上 |
| `app/common/service/export/*` | `ImportExport` | 同上 |
| `app/common/service/notice/*` | `NotificationSms` | 同上 |
| `app/common/service/oauth/*` | `IntegrationSecurity` | 同上 |
| `app/common/service/config/*` | `Settings` | 同上 |
| `app/common/service/org/*` | `Kernel\Organization` | 同上 |
| `web/src/store/modules/app/*` | `@peanut-admin/admin/shell` | 同上 |

### P2 — 需要设计（核心库可以提供通用框架，但具体实现有业务色彩）

| 当前位置 | 考虑 |
|---------|------|
| `app/common/service/payment/*` | 支付抽象可提取，但支付渠道配置是业务 |
| `app/common/service/storage/*` | 对象存储适配器可提取 |
| `app/common/service/dict/*` | 字典 CRUD 框架可提取 |
| `app/common/service/audit/*` | 审计框架可提取 |
| `app/common/service/diagnostics/*` | 诊断框架可提取 |

### 不应提取（保持 app-owned）

| 当前位置 | 理由 |
|---------|------|
| `app/common/service/article/*` | Peanut Admin 特有业务 |
| `app/common/service/decoration/*` | Peanut Admin 特有业务 |
| `app/common/service/member/*` | Peanut Admin 特有业务 |
| `app/common/service/finance/*` | Peanut Admin 特有业务 |
| `app/common/service/hot_search/*` | Peanut Admin 特有业务 |
| `app/common/service/wechat/*` | Peanut Admin 特有业务 |
| `web/src/views/*` (103 files) | 纯业务视图 |

---

## 6. 提取后的理想架构

```
┌─────────────────────────────────────────────────────┐
│  派生应用 (app-owned)                                │
│  ├── 业务视图 (views/)                               │
│  ├── 业务 API (controllers + logic)                  │
│  ├── 业务 Module (Modules/*)                         │
│  ├── peanut.overrides.ts  (前端覆盖声明)              │
│  └── config/peanut.php    (后端覆盖声明)              │
├─────────────────────────────────────────────────────┤
│  核心库 (Composer / npm，一键升级)                     │
│  ├── Kernel: Auth, Tenancy, Module, Permission ...   │
│  ├── 内置默认实现: TenantScope, Guard, Layout ...     │
│  ├── Override 注册机制                                │
│  └── 子包: FileMedia, TaskJob, ImportExport ...       │
├─────────────────────────────────────────────────────┤
│  脚手架模板 (managed, 可被升级器覆盖)                   │
│  ├── 项目骨架: compose.yaml, scripts/, deploy/ ...    │
│  ├── 构建配置: vite.config, tsconfig, webpack ...     │
│  └── 入口胶水: main.ts, bootstrap, route/index ...    │
└─────────────────────────────────────────────────────┘
```

**关键变化**：原来在"脚手架模板 app-owned"层的大量桥接代码，下沉到"核心库"层。派生应用只保留 **真正属于自己的业务代码** 和 **两个覆盖声明文件**。

---

## 7. 提取后的升级体验

| 动作 | 之前 | 之后 |
|-----|------|------|
| 核心框架升级 | `composer update` + 手动检查 app-owned 桥接代码 | `composer update` 完成，桥接自动跟随 |
| 脚手架升级 | `scaffold-upgrade` 只覆盖 298 managed 文件 | `scaffold-upgrade` 仍只覆盖 managed，但 managed 中的**胶水代码**大幅减少 |
| 业务不兼容风险 | 核心升级后 697 个 app-owned 文件中可能有桥接代码不兼容 | 只有真正的业务代码（约 400 文件）需要检查，且这些文件通过稳定 Override 合同与核心交互 |
| 派生应用开发者心智 | "哪些 app-owned 能改、哪些不该改？" | "app-owned 就是你的业务，随便改；框架的事交给 `composer update`" |
