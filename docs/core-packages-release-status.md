# Peanut Admin 核心包发布状态

> 核查日期：2026-08-10
> 核心仓：`peanut-opensource/peanut-admin`

## 结论

核心仓的收敛分支已经把原有 11 个 PHP 与 11 个 Web manifest 合并为两个公开 manifest。磁盘上保留的领域目录只是内部源码模块，不是 22 个待发布包，也不会要求应用逐个安装。

结合管理端、PC 与 UniApp 的实际消费图谱，正式方案只保留两个公开运行包：

| 生态 | 应用依赖 | 内容 |
|---|---|---|
| Composer | `peanut-admin/core` | 内核及认证、权限、设置、文件、任务、会员、内容、通知、支付等内部业务模块 |
| npm | `@peanut-admin/admin` | 管理端 Shell、认证权限、HTTP、扩展注册表和业务页面模块；通过 `./client`、`./client/nuxt`、`./client/uniapp` 子路径提供 PC/UniApp 共用的无 UI 业务能力与适配器 |

测试支持留在核心 monorepo 内，只作为仓内开发工具，不成为应用运行依赖。只有出现真实独立消费者、稳定公共 API 和独立发布节奏时，内部模块才允许拆成单独公开包。

## 当前事实

- 核心仓 `dev` 只有 `packages/php/composer.json` 与 `packages/web/package.json` 两个公开 manifest；原领域目录继续作为包内模块。
- Composer `peanut-admin/core@0.1.0-alpha.2` 已通过 Packagist 发布，并已由本应用的锁文件从公开 registry 安装。
- npm 已公开发布 `@peanut-admin/admin@0.1.0-alpha.2` 与 `0.1.0-alpha.3`；当前 `alpha` 指向 Alpha.3，`latest` 保持 Alpha.2。应用管理端锁定 Alpha.3。
- 核心仓不再部署重复的 GitHub Pages，只保留文档构建校验；2026-08-10 的 Documentation workflow 已成功。正式文档站仍由 Cloudflare Pages 托管。
- 本应用已消费 PHP 权限集合与 Web 权限 helper，但绝大多数 PHP 业务、管理端页面及 PC/UniApp 客户端逻辑仍在应用内，尚未完成领域迁移。

PC 已使用 Nuxt 3 + Element Plus，UniApp H5 使用 UniApp + Vue 3。核心包现已提供 `./client`、`./client/nuxt` 与 `./client/uniapp` 三个无 UI 子路径；本应用尚未迁移消费。端特有页面、组件、存储、导航和支付/OAuth 平台调用仍留在应用中。

因此现在不能宣称“核心业务已经全部进入核心包”，也不应继续逐个发布现有 22 个子包。

## 收敛原则

核心仓可以在源码层保留清晰的领域模块目录，但 package 边界不等于模块边界：

- PHP 内部模块统一由 `peanut-admin/core` 的 Composer autoload 和 ServiceProvider 装配。
- Web 管理模块统一由 `@peanut-admin/admin` 的管理端子路径导出，应用只面对稳定入口和覆盖注册表。
- PC/UniApp 共用逻辑由同一包的 `@peanut-admin/admin/client` 导出，通过端适配器接入 Nuxt 与 UniApp，不依赖任何 UI 库。
- `testing`、示例、构建脚本和 starter 不计入公开运行包。
- 应用不得使用几十个 path repository、复制源码或兼容层伪装标准依赖。

## 发布顺序

1. [已完成] 完成一个 PHP 包与一个管理端 Web 包的收敛门禁，内部模块目录继续保留。
2. [已完成] 在现有 npm 总包内新增无 UI client 子路径，不新增第三个 package manifest。
3. [已完成] 修复核心 CI，并完成固定候选聚合门禁、包投影、registry 安装和内部 Host 消费验证。
4. [已完成] npm Alpha.2/Alpha.3 与 Composer Alpha.2 已发布；产品化完成前不发布 `1.0.0`。
5. 在本应用先迁移认证与权限，再按系统、会员、内容、通知、支付迁移；每个领域完成后删除应用内重复实现。
6. 全部领域迁移完成后，应用才可声明核心能力已外置。

后续测试版本只在核心仓门禁全绿且产品应用完成一次 registry 消费验证后发布。任何 registry 凭据都不得写入仓库、命令输出或日志。
