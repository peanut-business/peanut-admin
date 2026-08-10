# PB08B 正式候选集成验收合同

> 状态：Frozen，待执行
>
> 应用实现基线：`04594943e5a854bff0452524bf97f6616855ff6e`
>
> 升级起点：`bc2e75ac6217d7defc44cd2b8e0c9e85a7cefc62`
>
> 核心只读基线：`7fbd445d8fa547830b7782a7ac147d9ed414e0fd`
>
> 当前验收 owner：`PB08B-RC-003`

## 1. 目标与候选定义

PB08B 只证明 PB03–PB08A 已完成实现可以组成一个可安装、可升级、只从公开 registry 解析依赖、可由生产 Compose 运行且品牌/文档一致的正式候选。它不重新验收 LikeAdmin parity、各领域业务矩阵、真实第三方渠道、核心候选或 SaaS，也不授权 PB09 发布。

候选从应用仓一个已提交、工作树干净的 Git 提交导出到隔离临时目录；Docker build context、数据库脚本和浏览器服务都必须来自该候选。验收合同与最终证据说明可以位于候选实现提交之后，但不得修改候选 Runtime。若验收中需要修改 PHP、SQL、锁文件、Docker、四端 Runtime 或品牌源，当前候选失败：只允许一次只读诊断，修复后冻结新候选并重新建立新的验收 owner，不能沿用通过结论。

运行时公开依赖仍只有 Composer `peanut-admin/core` 与 npm `@peanut-admin/admin`。核心仓保持只读，内部领域目录不是独立 package；核心仓既有未跟踪 `.playwright-cli/` 不删除、不纳入候选。

## 2. 封存证据与本轮新证据

以下证据只绑定，不重跑：

- `output/playwright/v02/` 的 LikeAdmin parity 与 30/30 路由；
- PB04–PB07 合同已绑定的 C01/C02、DE01-DE02、M01/M02、S01、F02、CH01–CH03、T01 领域/API/数据库/浏览器证据；
- `output/playwright/element-plus-baseline/summary.json` 的管理端代表域；
- PB03–PB07 各 Host owner 的聚焦测试结果；
- PB08A 品牌、安装 bootstrap、四端 typecheck/build 与官网静态门禁结果。

PB08B 必须在同一候选上新产生且只产生一次：隔离 registry/Docker 构建、实时空库安装、从升级起点前滚、生产 Compose 健康/路由，以及合并后的桌面/移动 Chromium。旧 `output/deployment/upgrade-rehearsal-20260807.json` 只有 24 条迁移，仅作历史基线，不能证明当前候选的 28 条迁移。

## 3. `PB08B-RC-001` 一次性矩阵

### RC01 候选与 registry

1. 记录候选 SHA、基础 SHA、Git 状态、四个 lockfile 摘要与生产 Dockerfile/Compose 摘要。
2. 锁文件不得出现 `path` repository、`file:`、`link:`、`workspace:` 或本地绝对路径；版本事实固定为 Composer `peanut-admin/core@0.1.0-alpha.2`，Web/PC `@peanut-admin/admin@0.1.0-alpha.3`，UniApp `@peanut-admin/admin@0.1.0-alpha.4`。
3. 使用隔离 Docker build cache 执行一次生产 `--no-cache` 构建。Composer、pnpm 与 npm 必须从 lockfile/公开 registry 完成安装；构建同时生成管理端、PC、UniApp H5 和 PHP/Nginx 镜像。不得挂载应用工作区的 `vendor/`、`node_modules/`，不得使用核心 path repository。
4. docs-site 的依赖/静态构建绑定 PB08A 已通过的同提交证据；PB08B 不再重复静态构建。

### RC02 实时数据库

全部数据库位于独立 Compose 项目和专用 MySQL volume，验收后删除。不得连接开发、历史验收或生产数据库。

1. **弱凭据零写入**：对独立空库以缺失或弱 `ADMIN_INITIAL_PASSWORD` 调用当前安装器一次，必须失败；随后 `information_schema` 中该库业务表数为 0。
2. **当前空库安装**：对另一空库使用一次合格随机初始密码启动生产 Compose；必须得到全部预期表、28 条 `applied` 迁移账本、唯一 root `admin`、非空菜单/配置，以及 `server/config/brand.json` 的全部 16 个 website 字段和值。安装输出、容器日志和证据不得出现密码、摘要或盐。
3. **基线前滚**：在第三个空库用升级起点 `bc2e75ac…` 的安装器建立 24 条账本基线，随后写入专用自定义管理员摘要/盐和自定义网站品牌哨兵；使用当前候选 `migrate.php` 前滚到 28 条，再运行一次 `--skip-if-installed` 和一次幂等 `migrate.php`。升级不得要求 `ADMIN_INITIAL_PASSWORD`，不得改变管理员摘要/盐或已有品牌哨兵，最终迁移状态为 `up_to_date` 且没有 failed/running 记录。
4. DB 验收只查询结构、账本、品牌、管理员与最低种子计数；不重跑领域 CRUD、支付、OAuth、通知或 parity 数据矩阵。

### RC03 生产 Compose 与路由

1. PHP、Nginx、scheduler 和 bundled MySQL 使用隔离项目名、专用 volume 与未占用的回环端口；Redis 不启用。
2. PHP/Nginx health 必须通过；`/healthz`、`/admin/`、`/pc/`、`/mobile/` 返回成功，`/api/index/config` 返回规范品牌 DTO。
3. Nginx 镜像内三端静态目录彼此独立，后端 `server/public/index.php` 仍存在；不得由前端产物覆盖 public 根入口。
4. HTTP/API 只检查候选装配、静态入口与品牌读取；核心业务结果继续绑定封存领域证据。

### RC04 Host/override 与唯一实现

1. 以 PB03 图谱和 PB04–PB07 最终合同为 owner；PB08B 不重跑各 Host 聚焦测试。
2. 候选锁定两个公开包并由生产 Composer/npm 安装；PHP `CoreServiceOverrides`、Web `peanut.overrides.ts`、PC/UniApp client 公共入口仍可由候选源码与构建产物定位。
3. 不允许 path/workspace 依赖、依赖目录修改、核心 deep import、第三个公开运行包或领域第二 Runtime。若静态核对发现边界变化，立即停止并返回相应领域重新冻结合同。

### RC05 唯一真实浏览器

使用一次 Playwright/Chromium 验收任务，在同一任务中切换桌面与移动 viewport；不得另开第二套浏览器验收：

- 桌面官网：首页导航、关键 CTA、文档门户、本地搜索、GitHub 链接与自定义 404；
- 移动官网：折叠导航、快速开始/文档入口、搜索或文档可达性与 404；
- 管理端桌面：默认 logo、名称、slogan、favicon、登录页，使用本次随机密码登录并打开一个代表只读内容页；
- PC 桌面：默认名称/logo/favicon 与一个公开页面；
- UniApp H5 移动：默认名称/logo、页面标题与一个公开页面。

只记录关键截图、URL/标题、可见品牌和失败 console/network 摘要。原生 UniApp 不增加真机或多平台组合；真实支付、短信、微信/OAuth 不调用。

### RC06 文档与发布边界

1. README、官网、开发、部署/升级、用户手册和版本页必须与候选的 PHP 8.3、28 条迁移、强初始密码、品牌生命周期、两包边界和 PB08B/PB09 状态一致。
2. 公开文档不得把历史验收域名/IP、局域网地址、固定密码、未实现 SaaS 或未执行 PB09 写成模板默认/已发布事实。
3. 应用许可证/provenance、`LICENSE`、`NOTICE` 与第三方清单仍是 PB09 前独立决策门禁；PB08B 不推断许可证，也不因技术验收通过而宣布正式发布。

## 4. 证据、清理与停止线

证据写入 `output/playwright/pb08b/`：`summary.json` 为唯一总入口，引用 registry/build、database、Docker、desktop/mobile 浏览器结果和最少截图。不得复制密钥、管理员密码、数据库密码、token、证书或完整容器日志；摘要只保留布尔结果、计数、版本、SHA 和脱敏错误。

验收结束必须删除专用容器、网络、volume、临时候选目录和浏览器会话；只保留已提交证据。清理只允许匹配本合同专用 Compose project 名和 `mktemp` 返回的精确目录，不操作其他项目容器/volume。

任何单项失败后最多做一次只读诊断并停止，不临时扩张为全量调试。没有用户新授权时，不访问真实商户/微信/短信、不部署公网、不推送分支、不合并 `dev/main`、不修改核心仓、不开始 PB09 或 SaaS。

## 5. 完成定义

PB08B 只有在 RC01–RC06 同一候选全部通过、`summary.json` 完整、临时资源清理完成且计划/合同已更新时才完成。通过表示技术正式候选成立；应用许可证/provenance 门禁未决时，PB09 仍不得开始。

## 6. 执行记录

### `PB08B-RC-001` — 编排失败，候选未判失败

- 同一实现候选 `0459494…` 的 RC01 唯一 `--no-cache` 生产构建通过；日志确认 Composer 从公开 registry 下载并安装 `peanut-admin/core@0.1.0-alpha.2`，管理端安装 `@peanut-admin/admin@0.1.0-alpha.3`，PHP、管理端、PC、H5 与 Nginx 目标均构建成功。
- 弱密码安装按预期拒绝并保持 0 表；`bc2e75ac…` 基线安装也成功得到 43 表、24 条账本、170 菜单与 59 配置。
- 随后的基线哨兵准备阶段失败。一次只读诊断确认旧基线 `pa_admin.salt` 是 `VARCHAR(16)`，排除字段宽度推断；但首版 shell 把预期弱密码非零退出也接入全局 `ERR` trap，并把多条哨兵断言共用一个粗粒度 stage。专用数据库已按合同清理，现有证据不能定位是哪条编排断言失败，因此 RC001 不形成数据库通过/候选失败结论。
- 未执行当前空库、升级、浏览器或 PB09；专用容器、网络、volume、镜像已删除，临时候选目录移入废纸篓。

### `PB08B-RC-002` — 旧品牌哨兵前提错误，候选未判失败

RC002 继续使用相同实现候选，不重复 RC01 无缓存/registry 验收。允许从已验证构建 cache 重建同摘要镜像；若 cache miss 导致重新访问 registry 或重新执行依赖安装，则停止并先修订本记录。数据库编排必须为预期弱密码失败临时禁用 `ERR` trap，并在账本计数、哨兵写入、升级、保留值、幂等、空库与 HTTP 断言前分别设置唯一 stage；管理员密码哨兵复用旧行现有 salt，只更新由该 salt 计算的摘要，不修改 salt 字段。

RC002 的缓存镜像重建 40 个步骤命中 cache，未重复 registry 安装；弱密码零写入、43 表/24 条账本基线安装通过，精确停在 `baseline_brand_presence`。一次只读诊断列出旧库 website 键：旧基线只有 `web_logo/web_favicon/login_image/shop_name/shop_logo/pc_logo/pc_title/pc_ico/pc_desc/pc_keywords/h5_favicon`，没有 `website/name`。因此失败来自验收脚本错误假定新字段已存在，不是升级 Runtime 失败；尚未执行 24→28 迁移、当前空库、HTTP 或浏览器。专用资源已再次清理。

### `PB08B-RC-003` — 当前 owner

RC003 继续绑定同一实现候选和 RC01 唯一构建。数据库哨兵改用旧基线确定存在的 `website/pc_title`，并在升级后同时验证该自定义值保持不变、Runtime `/api/index/config` 仍补齐完整规范 DTO；不得预先插入当前新增 website 字段来伪造升级输入。其余逐断言 stage、现有 salt 密码摘要、cache-only 镜像恢复和清理规则沿用 RC002。
