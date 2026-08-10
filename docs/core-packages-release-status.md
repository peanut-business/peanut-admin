# Peanut Admin 核心包发布状态

> 核查日期：2026-08-08  
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

- 核心仓 `feat/p1-override-registry` 分支当前只有 `packages/php/composer.json` 与 `packages/web/package.json` 两个公开 manifest；原领域目录继续作为包内模块。
- 两个 manifest 的当前资格版本均为 `0.1.0-alpha.2`。固定候选 `b0dc376c2147b98522764486342c9525fe5678ce` 已通过聚合门禁和包投影检查。
- npm 包 `@peanut-admin/admin@0.1.0-alpha.2` 已公开发布。2026-08-08 的隔离消费者已从公开 registry 安装成功，确认版本、14 个 exports 及其目标文件全部存在；发布 tarball SHA-256 为 `94b15ddcbe031b109e687b01c61002b343c8259d4b0745b05e64b391718b13ef`。
- npm 的 `alpha` 与 `latest` 当前都指向 `0.1.0-alpha.2`。`latest` 不是预期的预发布通道，但本轮修正因 npm 安全写权限返回 403 而停止，未继续创建新凭据。
- Composer 包 `peanut-admin/core@0.1.0-alpha.2` 尚未发布。固定投影包含 604 个文件，SHA-256 为 `176608c1602b0ccf8acf79a9755eb7417c25445330ccde7baddcae7df8620bdc`；公开 split 仓库与 Packagist 条目当前均不可见。浏览器已确认 Packagist 账户处于登录状态并具备提交入口，但生成仓库、包所有权、更新身份和发布批准仍未建立，批准状态仍为 `preflight-open / publication_authorized: false`。
- 本应用仍使用应用内 PHP、Arco 管理端以及 PC/UniApp 内置客户端逻辑，尚未迁移为上述两个公共依赖。

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
3. [部分完成] 修复 CI，并完成固定候选聚合门禁、包投影和内部 Host 消费验证；npm registry 隔离消费者已通过，Composer registry 消费仍待发布后执行一次。
4. [部分完成] npm Alpha.2 已发布；Composer Alpha.2 仍须在 split 仓库、Packagist owner、发布身份和审批门禁完成后发布。门禁稳定前不发布 `1.0.0`。
5. 在本应用先迁移认证与权限，再按系统、会员、内容、通知、支付迁移；每个领域完成后删除应用内重复实现。
6. 全部领域迁移完成后，应用才可声明核心能力已外置。

Composer split、Packagist owner/更新身份与发布批准仍是 Composer 发布前置条件。npm 临时发布令牌已进入删除流程，但 npm 要求账户密码二次确认；不得绕过该确认或把凭据写入仓库、命令输出和日志。未经可安装性验证，不发布更多测试 tag。
