# Peanut Admin 应用、核心包与发布契约

> 状态：Accepted Target，两个核心包已发布，应用领域迁移尚未完成
> 日期：2026-08-11

## 1. 产品与仓库边界

`peanut-business/peanut-admin` 是可运行的演示应用和新应用模板。开发者克隆模板后形成一个独立应用，在应用仓完成品牌、配置和业务开发。生产部署的是这个已存在的应用 release，不是在服务器再次克隆模板创建应用。

`peanut-opensource/peanut-admin` 是可复用核心能力仓。本应用的 LikeAdmin 业务能力已经独立验收，但目前仍实现在应用内部；迁移完成前不能把目标架构描述为当前事实。

## 2. 前端基线

- 管理端已统一为 Vue 3 + Element Plus，Arco 依赖、构建插件和源码组件已移除。
- PC 端已经使用 Nuxt 3 + Element Plus。
- UniApp 使用跨端组件体系，不强制套用 Element Plus。
- 管理端真实 Chromium 最低验收已覆盖登录、工作台、系统、文章、通知、财务、装修和渠道，证据见 `output/playwright/element-plus-baseline/summary.json`。
- UI 统一只适用于管理端和 PC Web。PC 与 UniApp 共用无 UI 的客户端业务内核，不共享页面组件。

## 3. 核心依赖边界

模块化用于组织代码，公开 package 用于稳定分发，两者不能机械地一一对应。正式目标只发布两个运行包：

| 生态 | 唯一公开包 | 内部入口与模块 |
|---|---|---|
| PHP | `peanut-admin/core` | kernel、认证、权限、数据权限、设置、文件、任务及核心业务域 |
| Frontend | `@peanut-admin/admin` | `./core`、`./shell` 和领域入口服务管理端；`./client`、`./client/nuxt`、`./client/uniapp` 服务 PC 与 UniApp 的 DTO、API 契约、认证会话、业务状态机、规则及端适配器 |

管理应用安装 PHP 与 Frontend 两个包；PC 和 UniApp 只消费 Frontend 包的无 UI client 子路径，并分别注入 Nuxt `$fetch`/cookie 与 `uni.request`/storage 适配器。`./client` 不得依赖 Element Plus、管理端页面或 UniApp 组件。测试工具、starter 和示例留在核心 monorepo 内。新模块默认进入上述包的内部目录；只有真实第二消费者要求独立安装、API 稳定且需要独立发布节奏时才允许重新评估拆包。

应用后端只保留 HTTP 装配、项目配置、应用专属模块和覆盖实现。应用管理端只保留启动入口、品牌主题、菜单装配、项目页面与覆盖注册。不得复制核心业务规则形成双实现。

## 4. 标准覆盖协议

覆盖必须通过稳定 key 和显式契约完成，禁止修改 `vendor/`、`node_modules/` 或复制核心类后静默替换。

PHP：

1. 核心定义 interface、DTO、错误和事件契约。
2. 核心 ServiceProvider 注册默认实现。
3. 应用通过单一配置声明 `contract => application implementation`。
4. 应用 Provider 最后加载，启动时校验实现类型、重复 key 与版本约束。
5. 业务代码通过依赖注入消费契约。

Web：

1. 核心为 service、component、page 和 route contribution 定义类型化 key。
2. 应用只在 `peanut.overrides.ts` 注册覆盖。
3. 启动器合并默认注册与应用覆盖，并拒绝未知 key、重复覆盖和不兼容版本。
4. Vite alias 只允许完整包替换，不作为业务覆盖协议。

Client：

1. 核心业务流程只依赖 transport、token storage、navigation 和 user feedback 等适配器接口。
2. PC 与 UniApp 在应用入口注册各自适配器，不复制认证、错误处理、DTO 或业务状态机。
3. 页面布局、平台 API、支付拉起和 OAuth 回跳等端特有交互留在对应应用中。
4. 客户端覆盖同样使用稳定 key、类型校验和版本约束，禁止通过复制源码形成分叉。

覆盖点属于公共 API，遵守 SemVer。

## 5. 生产发布契约

Docker 多阶段构建一次完成：

1. 构建 `web/` 到 Nginx 镜像的 `server/public/admin/`。
2. 构建 UniApp H5 到 `server/public/mobile/`。
3. 构建 PC Nuxt SPA 到 `server/public/pc/`。
4. 安装后端 Composer 生产依赖并运行 PHP-FPM。
5. 由 Nginx 统一暴露 `/admin/`、`/mobile/`、`/pc/`、`/api/` 和 `/storage/`。

三个静态客户端只写入自己的目录，不覆盖后端 public 根文件。生产宿主机只需要 Git、Docker 和 Compose；Node、PHP、Composer 均由构建或运行容器提供。宝塔反代 `127.0.0.1:18092`，Cloudflare 代理服务器域名。

## 6. 迁移顺序

1. [已完成 2026-08-07] 稳定当前三端 Docker 发布和首次空库启动。
2. [已完成 2026-08-10] 核心仓已收敛并发布一个 Composer 包和一个 npm 前端总包；Composer Alpha.2 与 npm Alpha.2/Alpha.3/Alpha.4 均可从公开 registry 安装。
3. [已完成 2026-08-11] 后端与管理端标准覆盖 Host 已落地；认证与权限已消费公开包。
4. [已完成 2026-08-11] 管理端迁移到 Element Plus，并通过真实 Chromium 代表业务域验收。
5. [已完成 2026-08-11] PC 与 UniApp 已迁移共用请求、认证和错误处理到无 UI client 子路径。
6. [进行中] 按系统、会员、内容、通知、支付迁移核心业务域，每域删除应用内重复实现并做一次最低业务验收。
7. 全部完成后，模板应用才切换为完全依赖核心包的正式基线。

SaaS 多租户仍是 `docs/design/saas-roadmap/` 中的路线图，不属于当前发布能力。
