# 主流框架功能横向对照

> 状态：Reference Complete（方向已于 2026-07-15 确认）
>
> 复核日期：2026-07-15
>
> 用途：核对 Peanut Admin 需要什么、不需要什么，不把市场宣传当成安全实现证明。

## 1. 标记说明

| 标记 | 含义 |
| --- | --- |
| 是 | 官方文档或已检查源码明确存在 |
| 部分 | 只覆盖部分能力、作为可选插件，或不同版本差异较大 |
| 前端 | 只解决前端展示/交互，不包含后端授权和数据隔离 |
| 未确认 | 本轮没有找到足够官方证据，不能猜测 |
| 不负责 | 不属于该项目的定位 |

LikeAdmin PHP 与 SaaS 主要用于比较 TP8 全栈后台体验；NiuCloud 用于比较站点、应用和插件装配；MineAdmin 用于比较权限、数据权限、插件和开发工具；ng-alain/Vben 用于比较前端脚手架与代码包组织。

## 2. 工程和交付形态

| 能力 | LikeAdmin PHP | LikeAdmin SaaS | NiuCloud SaaS | MineAdmin | ng-alain / Vben | Peanut Admin 决策 |
| --- | --- | --- | --- | --- | --- | --- |
| 可直接运行的管理后台 | 是 | 是 | 是 | 是 | 是，仅前端 | P1 达到可公开使用 |
| 前后端完整源码 | 是 | 是 | 是 | 是 | 不负责后端 | 是 |
| TP8 + Vue 3 + Vite + Element Plus | 是 | 是 | PHP/Vue，具体版本按发行版 | Hyperf + Vue 3 | Angular 或 Vue 前端 | 是，固定主线 |
| 前后端独立构建部署 | 是 | 是 | 是 | 是 | 前端应用可独立构建 | 是 |
| 一个仓库内 apps/packages | 未确认 | 未确认 | 插件目录体系 | 前后端/插件体系 | Vben 明确支持 | P0 使用 monorepo |
| 独立可发布前端包 | 未确认 | 未确认 | 部分插件前端 | 前端插件 | `@delon/*`、`@vben/*` | P1 从 monorepo 发布 |
| 独立可发布后端包 | ThinkPHP 本身支持 Composer | 同左 | 插件制品 | Composer/插件机制 | 不负责 | P0 先 path package，P1 发布 |
| CLI 安装/环境检查 | 是 | 是 | 是 | 部分 | 脚手架 CLI | P0 CLI，P1 可视化向导 |
| Docker 开发环境 | 版本/发行包相关 | 版本/发行包相关 | 版本相关 | 版本相关 | 前端不负责数据库 | P0 |
| 完整开发手册 | 是 | 是 | 是 | 是 | 是 | P0 最小手册，P1 完整站点 |

## 3. 登录、账号和租户

| 能力 | LikeAdmin PHP | LikeAdmin SaaS | NiuCloud SaaS | MineAdmin | ng-alain / Vben | Peanut Admin 决策 |
| --- | --- | --- | --- | --- | --- | --- |
| 管理员登录和会话 | 是 | 是 | 是 | 是 | 前端认证适配 | P0 |
| 邮箱凭证 | 版本/项目配置相关 | 版本/项目配置相关 | 未确认 | 未确认 | 由后端决定 | P0 第一凭证 |
| 手机号凭证 | 业务版本相关 | 业务版本相关 | 是/业务体系相关 | 未确认 | 由后端决定 | P1，与邮箱统一管理 |
| 一个账号绑定多种凭证 | 未确认 | 未确认 | 未确认 | 未确认 | 不负责 | P1 |
| 全局账号加入多个租户 | 不适用 | 未确认是否使用统一 Account/Member 模型 | 用户可创建/管理站点，但模型不同 | 官方介绍未证明具体模型 | 不负责 | P0 明确支持 |
| 登录后选择租户 | 不适用 | 主要通过租户域名进入 | 站点体系存在，入口流程不同 | 未确认 | 可做前端界面 | P0 默认流程 |
| 租户自定义域名 | 不适用 | 是 | 部分/站点渠道体系 | 未确认 | 不负责 | P1 |
| 平台操作员与租户成员分权 | 不适用 | 有平台端/租户端，内部授权模型需源码核验 | 有平台端/站点端 | 未确认 | 不负责 | P0 强制分开 |
| 一个租户管理多类别、每类多个业务对象 | 由业务开发 | 不属于官方通用模型证明 | 由应用/插件开发 | 由业务开发 | 不负责 | 明确支持 |
| 每租户一组独立业务表 | 不适用 | 本地快照可见部分租户专属表 | 主要使用 `site_id` 体系 | 未确认 | 不负责 | 明确不作为默认方案 |
| 共享表强制 tenant_id | 不适用 | 部分表存在，整体策略不同 | `site_id` 体系 | 官方介绍不足以证明守卫实现 | 不负责 | P0 不可绕过约束 |
| 独立数据库租户 | 不适用 | 未确认 | 未确认 | 未确认 | 不负责 | P2 部署策略 |
| 父子租户/集团 | 不适用 | 未确认 | 未确认 | 未确认 | 不负责 | P2，按真实合同关系设计 |

## 4. 成员、组织和权限

| 能力 | LikeAdmin PHP | LikeAdmin SaaS | NiuCloud SaaS | MineAdmin | ng-alain / Vben | Peanut Admin 决策 |
| --- | --- | --- | --- | --- | --- | --- |
| 管理员/用户管理 | 是 | 是 | 是 | 是 | 只消费后端数据 | P0 |
| 租户成员模型 | 不适用 | 有租户管理员，是否等同统一 Member 未确认 | 有站点用户/角色，模型不同 | 未确认 | 不负责 | P0 TenantMember |
| 部门树 | 是 | 是 | 未确认 | 是 | 前端可展示 | P0 最小能力 |
| 岗位 | 是 | 是 | 未确认 | 版本相关 | 前端可展示 | P1 |
| 多角色 | 是 | 是 | 是 | 是 | 前端可消费 | P0 |
| 动态菜单 | 是 | 是 | 是，菜单字典装载 | 是 | 是 | P0 Module manifest 装配 |
| 按钮/操作权限 | 是 | 是 | 是，菜单类型包含操作 | 是 | 前端支持显示控制 | P0，后端独立校验 |
| API 权限 | RBAC 接口校验 | 是 | 菜单声明 API/方法 | 是 | 只负责请求/显示 | P0 |
| 部门数据范围 | 版本/实现需核验 | 版本/实现需核验 | 未确认 | 官方明确数据权限和部门层级 | 不负责后端 | P0 |
| 指定门店/仓库对象范围 | 由业务开发 | 由业务开发 | 由插件/应用开发 | 需业务扩展 | 不负责后端 | P0 Module Provider |
| 列表与单对象统一数据权限 | 未确认 | 未确认 | 未确认 | 官方功能说明不足以证明完整算法 | 不负责 | P0 明确契约 |
| 多角色数据范围合并算法 | 未确认 | 未确认 | 未确认 | 未确认 | 不负责 | P0 明确交并规则 |
| 权限变更审计 | 部分日志能力 | 部分日志能力 | 操作日志 | 操作日志 | 不负责后端 | P0 |

## 5. 通用后台功能

| 能力 | LikeAdmin PHP | LikeAdmin SaaS | NiuCloud SaaS | MineAdmin | ng-alain / Vben | Peanut Admin 决策 |
| --- | --- | --- | --- | --- | --- | --- |
| 系统配置 | 是 | 是 | 是 | 是 | 前端配置能力 | P1，P0 只做必要配置 |
| 数据字典 | 是/版本相关 | 是/版本相关 | 开发文档提供 | 是 | 可缓存/展示 | P1 |
| 文件/对象存储 | 是 | 是 | 是/业务体系相关 | 附件能力 | 前端上传组件 | P1，复用 Flysystem |
| 素材库 | 是 | 是 | 应用体系相关 | 附件能力 | 前端组件 | P1 |
| 操作日志 | 是 | 是 | 是 | 是 | 不负责后端 | P0 审计基线 |
| 登录日志 | 是 | 是 | 未确认 | 是 | 不负责后端 | P0 |
| 异常/系统监控 | 部分 | 部分 | 未确认 | 是 | 前端可展示 | P1，对接成熟日志工具 |
| 队列管理 | 版本相关 | 版本相关 | 插件/版本相关 | 插件/版本相关 | 不负责 | P1，复用成熟队列 |
| 定时任务管理 | 版本相关 | 版本相关 | 插件/版本相关 | 可通过插件 | 不负责 | P1 |
| 导入导出 | 业务页面/版本相关 | 同左 | 应用/插件相关 | 版本相关 | 前端组件 | P1 |
| 备份恢复后台 | 未确认 | 未确认 | 未确认 | 未确认 | 不负责 | P1 适配器；P0 先有运维手册 |
| 国际化 | 部分 | 部分 | 多端/版本相关 | 版本相关 | 是 | P1 |
| 主题与布局配置 | 是 | 是 | 版本相关 | 前端支持 | 是 | P1 |
| 通知/消息中心 | 版本/业务相关 | 版本/业务相关 | 应用体系相关 | 版本相关 | Vben 有前端入口 | P1 通道和模板 |

## 6. 开发、扩展和产品装配

| 能力 | LikeAdmin PHP | LikeAdmin SaaS | NiuCloud SaaS | MineAdmin | ng-alain / Vben | Peanut Admin 决策 |
| --- | --- | --- | --- | --- | --- | --- |
| 前后端代码生成器 | 是 | 是 | 是 | 是/可选插件 | 前端 CLI/Schematics | P1 |
| OpenAPI/Swagger | 文档/版本相关 | 同左 | 未确认 | 是 | 消费 API | P1 自动生成，P0 固定契约 |
| 模块 manifest | 项目模块方式 | 项目模块方式 | 应用/插件元数据 | 插件 `mine.json` | 前端 package/app manifest | P0 自定义最小契约 |
| 租户开通某模块 | 不适用 | 套餐/租户能力相关 | 站点套餐明确装配应用和插件 | 未确认 | 不负责 | P0 TenantModule |
| 产品装配模板 | 不适用 | 套餐/版本相关 | 站点套餐 | 模板/市场 | 多应用配置 | P0 静态 ProductProfile |
| 插件安装/升级/卸载 | 未确认 | 未确认 | 是 | 是 | 前端插件/包，不等同后端生命周期 | P1 |
| 应用/插件市场 | 未确认 | 商业版本相关 | 是 | 是 | 不负责全栈市场 | P2 |
| 混合/前端/后端插件 | 未确认 | 未确认 | 多端插件目录 | 明确三种类型 | 前端包/插件 | P1 参考，不直接复制 |
| 模块间公开服务契约 | 由项目约定 | 由项目约定 | 插件/应用约定 | 插件服务机制 | 前端 package API | P0 强制所有权边界 |
| 禁止跨模块直接读写/JOIN | 未确认 | 未确认 | 未确认 | 未确认 | 不涉及后端 DB | P0 明确规则和检查 |
| 可复用前端核心包 | 未确认 | 未确认 | 插件前端能力 | 前端插件 | `@delon/*`、`@vben/*` | P0 包边界，P1 发布 |
| 可复用后端核心包 | 依赖 ThinkPHP/Composer | 同左 | 插件/框架能力 | Composer/插件 | 不负责 | P0 包边界，P1 发布 |

## 7. SaaS 运营和商业控制面

| 能力 | LikeAdmin PHP | LikeAdmin SaaS | NiuCloud SaaS | MineAdmin | ng-alain / Vben | Peanut Admin 决策 |
| --- | --- | --- | --- | --- | --- | --- |
| 平台租户管理 | 不适用 | 是 | 站点管理 | 官方介绍称支持 SaaS，细节未确认 | 不负责 | P0 最小 Tenant 管理 |
| 套餐/额度 | 不适用 | 是/版本相关 | 是 | 未确认 | 不负责 | P1 |
| 自定义租户域名 | 不适用 | 是 | 部分 | 未确认 | 不负责 | P1 |
| 平台支持/代运营会话 | 不适用 | 未确认 | 未确认 | 未确认 | 不负责 | P1/P2，显式授权审计 |
| API Key/Webhook | 项目扩展 | 项目扩展 | 插件扩展 | 插件扩展 | 前端消费 | P1 |
| 统一远程升级客户实例 | 不适用 | 未确认 | 未确认 | 应用市场更新不等于实例编排 | 不负责 | P2 独立 ops-console |
| 商业授权/证书 | 商业版相关 | 商业版相关 | 商业生态相关 | 应用市场认证 | 不负责 | P2，开源核心分离 |
| 遥测/远程诊断 | 未确认 | 未确认 | 未确认 | 系统监控不等于远程遥测 | 不负责 | P2，必须明确隐私和关闭能力 |

## 8. 最终取舍

### Peanut Admin 必须自己定义

- Account、Tenant、TenantMember 和 PlatformOperator 的安全链路。
- 租户守卫、数据权限 Provider 和跨租户测试契约。
- Kernel、Module、TenantModule 和 ProductProfile 的最小关系。
- 一个租户管理多类别、每类多个业务对象，以及成员管理一个或多个同类目标的通用边界。
- 模块数据所有权和禁止跨模块内部访问的规则。

这些是现有参照项目没有直接替我们解决的差异化核心。

### Peanut Admin 应复用成熟方案

- ThinkPHP 8 的容器、ORM、验证、缓存、队列等框架能力。
- Vue 3、Vite、Element Plus、Vue Router、Pinia。
- Composer/npm/pnpm 的包和 workspace 机制。
- Flysystem、OpenAPI、日志、密码哈希、邮件短信 SDK 等成熟库。
- LikeAdmin 的开箱体验、NiuCloud/MineAdmin 的插件思路、ng-alain/Vben 的前端包化方式。

### Peanut Admin 不纳入核心

- 商品、库存、门店、仓储、销售、财务等真实业务实现。
- Web SQL 编辑器和自研数据库管理工具。
- 第一版微服务、分布式事务、父子租户和通用跨租户协作。
- 第一版插件市场、统一远程升级和商业授权控制面。
- 为了看起来通用而建立 Application、Entry、Portal、SystemInstance 等无现实需求的运行时表。

## 9. 证据来源

- [LikeAdmin PHP SaaS 基础说明](https://doc.likeadmin.cn/php-saas/develop/base.html)
- [LikeAdmin PHP SaaS 部署与租户域名](https://doc.likeadmin.cn/php-saas/deployment/general.html)
- [NiuCloud 站点管理与站点套餐](https://doc.niucloud.com/saasUse.html?keywords=%2FUserGuide%2FPlatformFunctions%2FWebsiteManagement)
- [NiuCloud 菜单与权限开发](https://doc.niucloud.com/saas.html?keywords=%2FpluginDev%2FmenuDev)
- [MineAdmin 功能介绍](https://doc.mineadmin.com/v3/guide/introduce/mineadmin.html)
- [MineAdmin 插件系统](https://doc.mineadmin.com/v3/plugin/index.html)
- [ng-alain 架构与 @delon 包](https://ng-alain.com/docs/architecture/en)
- [ng-alain 与后端交互边界](https://ng-alain.com/docs/server/en)
- [Vben Admin 基本概念](https://doc.vben.pro/en/guide/essentials/concept.html)
- [Vben Admin 目录结构](https://doc.vben.pro/en/guide/project/dir.html)
- [Vben Admin Access Control](https://doc.vben.pro/en/guide/in-depth/access.html)

所有“未确认”项都不能作为 Peanut Admin 删除或实现某能力的唯一依据。进入真实依赖复用前，还要完成版本、源码、许可证、维护状态和安全记录审查。
