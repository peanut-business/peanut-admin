# 市场框架对照与取舍

> 状态：Reference Complete（方向已于 2026-07-15 确认）
>
> 调研截止：2026-07-15

## 1. 对照目的

本次不是证明 Peanut Admin 比其他框架更复杂，而是回答三个问题：

1. 成熟项目已经解决了什么，哪些能力可以直接采用或借鉴。
2. 哪些项目只解决前端或后台管理，不能被误当成完整 SaaS 底座。
3. Peanut Admin 因“一租户管理多门店、仓库等对象”而需要增加什么最小差异。

证据边界：在线文档按 2026-07-15 可见内容记录，后续可能变化；LikeAdmin SaaS 的表创建观察来自本地快照 commit `636023111ae0`，NiuCloud 目录/站点模型观察来自本地快照 commit `6d8afdad0403`。本轮没有把任何外部实现认定为安全基线，也没有完成逐文件许可证复用审查。

## 2. 总体对照

| 项目 | 类型和主要技术 | 截止调研日观察到的做法 | 不直接照搬的部分 | 对 Peanut Admin 的启示 |
| --- | --- | --- | --- | --- |
| LikeAdmin PHP | TP8 + Vue 3 的全栈后台 | 登录、权限、部门、岗位、菜单、日志、文件、配置、代码生成器，开箱即用 | 普通版不解决完整多租户；项目级业务功能较多 | 作为“能用后台”和技术栈的第一参考 |
| LikeAdmin SaaS PHP | 平台端 + 租户端 + 多端的 SaaS 全栈 | 平台管理租户、租户后台、套餐/域名等 SaaS 操作 | 源码存在按租户编号创建一组业务表的做法；不适合我们默认的一租户多对象和统一业务模块 | 借鉴产品体验和基础功能，不照搬每租户一组表 |
| NiuCloud Admin SaaS | PHP SaaS + 应用/插件体系 | `site_id` 隔离、站点套餐装配应用和插件、插件生命周期、多端目录 | Application、addon、site 等术语在不同层次有交叠 | 最接近“客户开通不同业务组合”的市场参考 |
| MineAdmin | Hyperf + Vue 3，前后端分离 | 用户、角色、菜单、组织、数据权限、日志、附件、代码生成、插件生命周期和前端插件钩子 | 后端技术栈不是 TP8；不能直接复制实现 | 借鉴插件机制、数据权限和应用市场边界 |
| ng-alain / @delon | Angular 前端脚手架和包族 | 脚手架与 `@delon/auth`、`@delon/acl`、`@delon/theme` 等可复用包分离 | 只有前端，不负责后端、租户和业务数据 | 证明前端核心包应独立版本化、可被其他项目安装 |
| Vben Admin | Vue/Vite 前端 monorepo | `apps/` 下多个可独立构建应用，`packages/` 下共享能力 | 不是全栈 SaaS，也没有后端领域边界 | 借鉴前端应用与共享包的 monorepo 组织 |
| ThinkPHP 8 | PHP 框架及应用骨架 | `topthink/think` 项目骨架依赖独立 `topthink/framework` 核心包 | 不提供完整后台和 SaaS 业务能力 | 证明后端可运行项目与 Composer 核心包应分离 |
| ABP | 企业应用框架 | Tenant 是 SaaS 客户边界；Host 与 Tenant 分开；共享库按 TenantId 过滤，也支持独立库 | 技术栈和整体复杂度远高于本项目 | 借鉴租户定义、Host 控制面和数据库策略，不复制庞大抽象 |

## 3. 三类框架不能混为一谈

### 3.1 单独前端脚手架

ng-alain、Vben Admin 解决布局、路由、权限展示、组件、构建和前端工程复用。它们不能替代后端权限校验、租户隔离和数据权限。

Peanut Admin 应借鉴其包化方式，但不能因为前端有菜单和 ACL 就认为 SaaS 已经完成。

### 3.2 单独后端框架或核心包

ThinkPHP/Hyperf 解决请求、ORM、容器、队列等技术基础。它们不等于可直接交付的管理后台。

Peanut Admin 后端核心包必须建立在 ThinkPHP 8 之上，不重写框架已有的容器、ORM、验证、队列和缓存。

### 3.3 完整前后台脚手架

LikeAdmin、NiuCloud、MineAdmin 提供可运行后台，是 Peanut Admin 功能清单的主要比较对象。

Peanut Admin 的最小差异只有两点：

1. 从第一版统一支持 Tenant、TenantMember 和数据隔离，不再维护基础版/SaaS 版两套核心。
2. 同一租户能管理多个门店、仓库等业务对象，并让多个产品组合复用商品、库存等模块。

除此之外应优先复用成熟库和常见做法，不自造新的身份协议、ORM、任务调度器、文件 SDK 或前端组件库。

## 4. 关键源码观察

LikeAdmin SaaS 本地参考源码 `server/app/platformapi/service/TenantCreatService.php` 会创建类似 `la_tenant_admin_{tenantSn}`、`la_tenant_admin_dept_{tenantSn}` 的租户专属表。

这能提供较强物理分隔，但会增加迁移、索引、统计、跨租户运营、备份和升级成本。Peanut Admin 第一版推荐共享表并强制 `tenant_id`，后续只在真实大客户或合规需求出现时增加独立数据库部署。

NiuCloud 的站点套餐可以预设应用和插件，MineAdmin 的插件有安装/升级/卸载生命周期。这证明“产品装配”和“可安装插件”都有市场依据，但二者不能等同：装配清单决定启用什么，插件是交付这些能力的一种制品形式。

## 5. 明确借鉴什么

| 能力 | 主要参考 | Peanut Admin 处理 |
| --- | --- | --- |
| TP8 + Vue 3 可运行全栈 | LikeAdmin PHP | 保留主技术栈与上手体验 |
| SaaS 平台与租户端体验 | LikeAdmin SaaS | 借鉴功能，不照搬每租户建表 |
| 站点/租户的能力装配 | NiuCloud | 提炼为产品方案和租户能力开通 |
| 插件生命周期 | MineAdmin、NiuCloud | 放入 P1，先定义边界再实现市场 |
| 前端包族 | ng-alain、Vben | 独立前端核心包仓库和版本 |
| 后端骨架/核心包分离 | ThinkPHP | 独立 Composer 包仓库 |
| Tenant/Host/共享库原则 | ABP、Azure 架构指南 | 统一 Tenant 根边界和显式 Host 控制面 |

这些项目用于证明某种功能或组织方式在市场中存在，不直接证明 Peanut Admin 必须采用相同仓库数量或安全实现。MineAdmin/NiuCloud 的插件与数据权限是功能参照，不能替代我们的权限威胁模型和越权测试。

## 6. 明确不做什么

- 不把旧 base-framework 的抽象层数当作先进程度。
- 不复制 LikeAdmin SaaS 的每租户一组表作为默认方案。
- 不把菜单配置当成模块系统。
- 不为了“可拆服务”在第一版引入微服务、消息最终一致性和分布式事务。
- 不重写 ThinkPHP、Vue Router、Pinia、Element Plus、JWT/Session 库、Flysystem 等成熟基础能力。
- 不因为 NiuCloud/MineAdmin 有应用市场，就把应用市场列为 P0。

## 7. 官方资料

- [LikeAdmin 官网](https://www.likeadmin.cn/)
- [LikeAdmin PHP SaaS 开发文档](https://doc.likeadmin.cn/php-saas/develop/base.html)
- [LikeAdmin PHP 后台开发文档](https://doc.likeadmin.cn/php/develop/admin.html)
- [NiuCloud SaaS 使用文档](https://doc.niucloud.com/saasUse.html)
- [MineAdmin 介绍](https://doc.mineadmin.com/v3/guide/introduce/mineadmin.html)
- [MineAdmin 插件系统](https://doc.mineadmin.com/v3/plugin/index.html)
- [ng-alain 架构](https://ng-alain.com/docs/architecture/en)
- [Vben Admin 项目结构](https://doc.vben.pro/guide/project/dir.html)
- [ThinkPHP 8 应用骨架](https://github.com/top-think/think)
- [ABP 多租户架构](https://abp.io/docs/8.2/framework/architecture/multi-tenancy)
- [Microsoft Azure 多租户模型](https://learn.microsoft.com/en-us/azure/architecture/guide/multitenant/considerations/tenancy-models)
