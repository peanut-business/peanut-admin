# 仓库、应用与代码包边界

> 状态：Accepted and Recalibrated（2026-07-15），已通过 48 号复审，等待新编码批准

## 1. 推荐先建立一个公开 monorepo

第一阶段只建立：

```text
peanut-opensource/peanut-admin
```

一个 Git 仓库内同时包含独立前端应用、独立后端应用、前端核心包和后端核心包。这里的“同仓”只表示一起版本管理，不表示代码混在一起，也不影响前后端分别构建和部署。

这样仍能满足“别的项目安装特定能力”：Composer/npm 包从第一天拥有独立 manifest 和 public API；P0 先使用 Composer path repository 和 pnpm workspace 验证，P1 再从 monorepo 发布到包仓库。

私有 DCS、客户交付和商业控制台不放入 `peanut-opensource`；未来采用 Peanut Admin 时，只通过版本化依赖消费。Finance Manager 已基于 LikeAdmin PHP 开发并基本成型，继续沿用现有技术基线，不迁移 Peanut Admin，也不作为首批消费方。

## 2. 为什么暂不建立三个 Git 仓库

包边界和 Git 仓库边界是两件事。

- ng-alain/ThinkPHP 证明了应用骨架和核心包应有稳定依赖边界。
- Vben 证明 `apps/` 与 `packages/` 可以在 monorepo 内独立开发和构建。
- 这些案例不能直接证明 Peanut Admin 在核心 API 尚未稳定时就必须分成三个远程仓。

过早分仓会立即增加原子提交、兼容矩阵、跨仓 CI、版本发布和问题定位成本。等下列条件至少满足两项后再评估拆分：

1. DCS、客户项目或其他新项目中至少有两个真实 Peanut Admin 消费者。
2. 核心包 public API 连续两个发布周期保持稳定。
3. 前后端核心包有明显不同的维护团队或发布节奏。
4. monorepo CI、权限或发布流程已经成为实际瓶颈。

未来如需拆分，候选仓库为 `peanut-admin-php` 和 `peanut-admin-web`；当前不创建、不冻结名称。

## 3. 第一阶段目录

```text
peanut-admin/
├── backend/                 # 可运行 ThinkPHP 8 后端应用
│   ├── app/                 # 项目装配、控制器和可选 Module
│   ├── config/
│   ├── database/            # 仅项目装配层迁移和种子
│   ├── public/
│   ├── tests/
│   └── composer.json
├── frontend/                # 可运行 Vue 3 + Vite + Element Plus 应用
│   ├── src/                 # 页面、路由和产品装配
│   ├── tests/
│   └── package.json
├── packages/
│   ├── php/                 # 可独立发布的 Composer 包
│   └── web/                 # 可独立发布的 npm 包
├── templates/               # 模块、页面和项目生成模板
├── examples/                # 虚构最小示例，不承载正式业务
├── docs/                    # 开发手册网站源文件
├── scripts/                 # 安装、检查、发布和同步脚本
├── docker/                  # 本地开发环境
├── LICENSE
└── README.md
```

前端和后端可以分别安装依赖、测试、构建和部署。使用者一次克隆能获得匹配版本的全栈系统。

## 4. Kernel、Module、Plugin、Package

这是必须分清的四层：

| 概念 | 是什么 | 能否关闭 | 是否有安装生命周期 | 例子 |
| --- | --- | --- | --- | --- |
| Kernel | 所有受保护请求都依赖的安全与运行内核 | 不能 | 随系统版本升级 | 认证、租户、成员、授权、数据权限守卫、审计、Module 注册 |
| Module | 有明确数据和规则所有权的可选功能单元 | 可以 | 迁移、启用、停用 | 文件、字典、通知，以及后续商品、库存 |
| Plugin | 可安装的交付制品，可贡献一个或多个 Module/前端扩展 | 可以 | 安装、升级、卸载 | P1 官方或第三方扩展 |
| Package | Composer/npm 代码依赖和复用单位 | 通常没有运行时开关 | 包管理器升级 | auth、tenancy、access、layout 包 |

认证和租户隔离属于 Kernel subsystem，不能被设计成可关闭的 TenantModule。TenantModule 只控制可选 Module 对某租户是否开通。

## 5. 后端核心包

第一阶段不拆十几个 Composer 包，只建立少量真实边界：

```text
packages/php/
├── kernel/                  # 上下文、认证、租户、成员、授权、审计、模块注册
├── data-permission/         # 查询谓词和单对象动作授权契约
└── testing/                 # 跨租户、权限和模块测试辅助
```

只有出现独立消费者和稳定 public API 后，才从 `kernel` 提取 `auth`、`tenancy`、`membership` 等更小包。目录多不等于复用能力强。

不进入核心包：产品控制器、商品/库存/门店业务、页面菜单、客户定制、部署密钥和演示数据。

## 6. 前端核心包

第一阶段同样保持少量包：

```text
packages/web/
├── admin-core/              # API client、认证状态、租户切换、访问控制和稳定公共类型
├── admin-shell/             # 布局、菜单容器和扩展点
└── testing/                 # 前端测试辅助
```

登录页、租户管理页、系统管理页、最终路由和产品主题留在 `frontend/`。只有多个页面/项目反复使用且 API 稳定的组件，才从应用提取到包。

## 7. ProductProfile、Application、Entry 和 Client

| 概念 | 第一版处理 |
| --- | --- |
| ProductProfile | 静态装配模板，初始化租户开通的 Module、菜单和默认配置；P0 不建动态产品中心 |
| Client | 实际构建运行的 Admin、POS、移动端或小程序 |
| Application | P0 不建运行时表；动态多品牌、域名、版本和渠道管理出现后再引入 |
| Entry | P0 删除；Client、路由、菜单和租户解析已经承担入口职责 |

能力生效必须同时满足：部署中安装了 Module、TenantModule 对租户开通、TenantMember 有功能和数据权限。ProductProfile 只初始化开通项，不是授权依据。

## 8. 模块数据所有权

- 每张表、ORM Model、Repository 和迁移必须有唯一所属 Module 或 Kernel subsystem。
- 其他 Module 不得直接读、写、JOIN、导入其内部 Model 或执行其迁移。
- 模块间读取使用公开查询契约或数据所有者发布的读模型。
- 统计报表使用显式投影，不能把临时跨表 SQL 变成业务依赖。
- 核心包迁移随所属包发布，业务模块迁移随模块发布。
- 脚手架负责发现、排序和执行迁移；`backend/database/` 只保存项目装配层迁移和种子。

同一数据库允许提升第一版开发和事务效率，但不是任意跨表调用的许可。

### 文件归属判断

| 内容 | 默认归属 |
| --- | --- |
| Account/Tenant/TenantMember 上下文、守卫、审计契约 | `packages/php/kernel` |
| 数据权限谓词和单对象校验契约 | `packages/php/data-permission` |
| 某 Module 的实体、服务、Repository、迁移 | `backend/app/Modules/<PascalSegments>/` |
| 某 Module 的控制器和 API 装配 | `backend/app/Modules/<PascalSegments>/Http/` |
| 某 Module 的页面、路由和前端状态 | `frontend/src/modules/<kebab-key>/` |
| 稳定且跨项目复用的前端认证/租户/访问控制 | `packages/web/admin-core` |
| 最终 Admin 布局和菜单容器 | `packages/web/admin-shell`，项目主题留在 `frontend/` |
| Module 菜单、权限和迁移声明 | 该 Module 的 manifest |
| 新项目/新 Module 的生成源 | `templates/` |
| 只用于教学和验收的虚构功能 | `examples/` |

只有“至少两个真实消费者、public API 明确、独立版本有价值”时，应用代码才提取到 Package。不能为了目录漂亮提前抽包。

## 9. 发布规则

- P0 使用 Composer path repository 和 pnpm workspace 验证包边界。
- P1 需要外部项目消费时，从 monorepo 发布 Composer/npm 包。
- 不使用 Git submodule，也不让私有项目直接复制核心源码。
- 内部包使用语义化版本和变更日志；私有产品锁定明确版本，不依赖 `dev` 分支。
- 脚手架发布记录兼容的 PHP 包、Web 包和 schema 版本。
- 发布前在干净环境验证安装，在旧版本副本验证升级。
- 循环依赖、跨模块内部引用和跨租户访问由静态检查和测试阻止。

## 10. 旧仓迁移

确认后按以下顺序执行，而不是立即改名：

1. 确认旧仓干净并创建冻结标签/提交记录。
2. 形成 `KEEP / REWRITE / DROP` 资产清单，核对来源、许可证和密钥泄露风险。
3. 在 `/Users/xing/Documents/company-os/repositories/peanut-admin/` 建立独立干净仓库。
4. 只迁移确认后的资产，并用新测试验证行为。
5. 新仓稳定后，再把旧目录改名为 `base-framework-legacy`；先检查脚本和文档中的绝对路径。

旧仓不删除、不重写历史、不直接推送公开。远程创建后再更新 `company-os/repos.md` 的实际地址和 commit。
