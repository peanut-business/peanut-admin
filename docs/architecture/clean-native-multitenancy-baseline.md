# Peanut Admin 原生多租户干净基线

> 状态：实施中
>
> 授权日期：2026-08-16
>
> 适用范围：下一主版本的源码、fresh install、create-app、当前 Runtime 与公开文档。

## 1. 决策

下一主版本以当前多租户产品能力为基础重建干净安装基线，不提供 `v1.1.x` 数据库或
脚手架的原地升级路径。旧 tag、Release 和 Git 历史只用于追溯，不进入当前 Runtime、
Schema、安装包、create-app inventory 或日常文档。

这不是重写整个应用。ThinkPHP、Vue、Nuxt、UniApp、已通过 Tenant 隔离的业务能力和
公共 Core 合同继续复用；身份桥、安装 Schema 和明确的兼容双写收敛为唯一原生实现。

## 2. 身份不变量

- `Account/Credential` 是管理成员唯一登录身份。
- `TenantMember` 是 Account 在 Tenant 内的唯一成员身份。
- Core Role/Permission/Department 是管理授权和组织的唯一权威模型。
- `PlatformOperator` 继续与 TenantMember 分离。
- `pa_member` 是客户侧业务会员和登录模型，不与管理 Account 合并。
- 管理请求的 Tenant 只来自已验证会话；请求参数、Host 默认值或 legacy map 不能改变它。

完成后，当前 Runtime 不得引用：

- `pa_legacy_admin_tenant_map`；
- `pa_legacy_role_tenant_map`；
- `pa_legacy_dept_tenant_map`；
- 只为单租户升级存在的 `pa_default_tenant_bootstrap` 状态账本。

## 3. Schema 与安装不变量

- Standalone 和 `multi-tenant` 都从空数据库执行同一套 canonical baseline。
- Standalone 仍创建一个正式默认 Tenant；它不是 legacy bootstrap 结果。
- 安装器直接创建原生 Account、Credential、TenantMember、owner Role 和会话所需结构。
- 当前 migration inventory 只包含新基线之后的追加式 migration，不执行旧应用 adopt、
  回填、镜像或过渡映射。
- create-app 生成物与源仓 fresh install 使用相同 Schema、Runtime 和测试合同。
- 明确的余额、流水和状态兼容镜像只能保留一个权威字段；删除前逐域确认读写消费者。

开发数据库不原地升级。采用下一主版本时重新创建空数据库并重新安装。

## 4. 官方能力的强制 Tenant Gate

只要能力随当前版本交付，无论它仍是 app-owned Runtime 还是未来官方 Plugin，都必须：

1. 明确属于 Tenant、平台或实例，禁止含糊的可空 `tenant_id`。
2. 所有 SQL 读写使用可信 TenantContext，并在唯一键和关联查询中保持 Tenant 维度。
3. 文件、对象 key、缓存、锁、任务、导出和临时文件使用 Tenant namespace。
4. OAuth、支付、短信和其他匿名回调先解析可信 Tenant，再验签和改变业务状态。
5. Tenant 暂停、Module 未开通、伪造 Tenant 或跨 Tenant 资源 ID 时 fail closed。
6. 至少有两个 Tenant 的读取、写入和拒绝断言；外部能力使用本地可信 transport fixture。

不满足 Gate 的非核心能力必须在修复后交付，或从版本 Runtime、菜单和文档中退出。不能
标记为“可选支持多租户”。本轮不把现有 app-owned 能力重构为 Plugin。

## 5. 文档边界

当前公开文档只说明 fresh install、原生身份、模块开发、多租户不变量和当前交付能力。
旧升级命令、legacy adopt、双写兼容和旧阶段状态不得继续出现在快速开始或日常开发路径。

`docs/design/saas-roadmap/`、已发布 Release 快照和历史架构合同保留原文，但必须从当前
导航中明确标记为历史输入。DCS 仍是独立派生应用；本仓只保留采用边界，不实现或宣称
拥有 Party、Store、Warehouse、Supplier、Product、Pricing、Inventory、Procurement 或
Trade Runtime。

## 6. 最低验收

- Standalone 与 `multi-tenant` 各完成一次空库安装和 `current` 检查。
- 最终 Schema 不存在 legacy map、退役 bootstrap 状态和已确认的兼容镜像。
- 管理员登录、首 owner、Tenant 切换、RBAC、暂停拒绝通过聚焦验证。
- 随版本交付的官方能力通过各自 Tenant isolation Gate。
- create-app 生成一次，并验证其 inventory 和安装入口与源仓一致。
- 管理端完成一次真实浏览器登录与 Tenant 切换 smoke。
- 当前文档通过差异、stale-facts、链接、inventory 和 VitePress 构建检查。

不运行旧版本升级、全量历史回归、性能、恢复、真实支付/短信/OAuth、多客户端组合或生产
部署 Gate。
