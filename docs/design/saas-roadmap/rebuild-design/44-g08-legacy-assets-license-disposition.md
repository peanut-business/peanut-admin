# G-08 旧资产处置与许可证清单

> 状态：Reviewed（2026-07-15）；资产结论继续有效，等待 48 号复审后的新编码批准
>
> 审计对象：`/Users/xing/Documents/Dev/Project/base-framework/`
>
> 本文只决定旧资产能否进入未来新仓，不授权初始化新仓或编写运行时代码。

## 1. 先给结论

旧 `base-framework` 不能通过改目录名、改 remote 或继续执行 R-02 变成 Peanut Admin 正式仓库。

本次重新审计后的结论比旧 V4 的“保留 Git 历史、逐文件改造”更严格：

1. 新公开仓使用干净 Git 历史。
2. 旧仓没有任何运行时代码可以原样迁移。
3. ThinkPHP、Vue、Vite、Element Plus 等宿主骨架从官方版本重新安装或生成。
4. 旧仓可以提供问题证据、测试意图和工程经验，但具体代码、字段、命名和计划不作为兼容目标。
5. 旧仓永久保留为本地历史快照；新仓稳定前不删除、不改名、不配置 remote。

这不是否认过去工作的价值。过去的工作帮助识别了身份、权限、数据隔离和脚手架边界中的错误；但把这些实验提交直接公开或继续改造，会把已否决概念、具体业务示例和不完整许可证一起带进新产品。

## 2. 为什么低上下文 Agent 会继续在旧仓开发

原因已经用旧仓冻结前的真实文件确认：

- 根 `README.md` 声称旧仓是 Peanut Admin 正式仓库。
- 根 `AGENTS.md` 声称 V4 已批准，下一步必须执行 R-02。
- `docs/v4/implementation-readiness.md` 声称 `next authorized task: R-02`。
- `docs/plans/2026-07-14-v4-runtime-implementation-plan.md` 标记为 `approved-to-start`。
- 本地目录名仍为 `base-framework`，但文档又把它解释为迁移期间的正式路径。

因此，旧开发位置不是 Agent 随机选错，而是旧仓的顶层事实源给出了错误授权。只在 company-os 里改变口径不足以阻止再次发生，必须在旧仓根部建立停止线。

## 3. 已建立的冻结点

| 项目 | 结果 |
| --- | --- |
| 冻结前分支 | `dev` |
| 冻结前 HEAD | `88f0eee08f13b08d10315e5802e4e49850294d09` |
| 冻结提交 | `45702df1880bcf9aad333e761ed6a9b03c1b2077` |
| 冻结标签 | `legacy-freeze-2026-07-15` |
| 冻结后工作区 | clean |
| Git remote | 无 |
| 冻结门禁 | 旧仓 `./scripts/check` 通过，并明确阻塞全部历史 Runtime 计划 |

冻结提交只修改旧仓：

- `README.md`
- `AGENTS.md`
- `LEGACY-FREEZE.md`
- `scripts/check`

旧仓后续只允许只读审计。不得继续 R-02，也不得把冻结标签理解成可发布版本。

## 4. 分类定义

| 分类 | 本次定义 |
| --- | --- |
| KEEP | 可以不改内容直接进入新公开仓 |
| REWRITE | 目标能力或工程经验有用，但必须从当前 G-01 至 G-09 和官方上游重新实现 |
| DROP | 不进入新仓；只在冻结旧仓和 Git 历史中保留 |

本次 `KEEP = 0`。冻结文件是“旧仓保留”，不是“迁入新仓 KEEP”。

## 5. 当前 92 个 tracked 资产的闭合处置

冻结提交后，旧仓共有 92 个 tracked 文件。以下路径组互斥并覆盖全部文件。

| 路径组 | 数量 | 处置 | 新仓动作 |
| --- | ---: | --- | --- |
| `README.md`、`AGENTS.md`、`LEGACY-FREEZE.md` | 3 | DROP | 只保留在旧仓作为停止线；新仓重新写自己的入口 |
| `.env.example`、`.gitignore`、`compose.yaml`、`docker/**` | 5 | REWRITE | 按新目录、变量和官方镜像重新生成，禁止复制旧 secret/config 名 |
| `backend/**` 当前占位和 ThinkPHP 文件 | 7 | REWRITE | 从官方 ThinkPHP 8 宿主重新生成并核对来源；旧文件不复制 |
| `docs/v4/**` | 27 | DROP | Application/Entry/Entitlement/SystemInstance 等旧 V4 不是当前事实源 |
| `docs/plans/**` 五份旧计划 | 5 | DROP | 禁止执行 V2/V3/V4/R-00..R-18 历史任务 |
| 三份旧概念/业务映射文档 | 3 | DROP | `core-concept-alignment-v2`、`dcs-model-mapping`、旧 `operation-context` |
| `docs/decisions/dependencies/**` | 8 | DROP | 版本、包集合和架构目标已变化；P0-A 重新做 dependency DDR |
| `docs/content-status.yml`、`docs/README.md` | 2 | REWRITE | 保留文档有效性清单机制，不复制旧状态和导航 |
| 其余通用架构、标准、API、测试、部署文档 | 28 | REWRITE | 只根据 G-01 至 G-09 重新编写；不得逐段搬运旧模型 |
| `scripts/check-doc-content-status` | 1 | REWRITE | 保留结构化 YAML 清单和失败语义，按新仓目录重新实现 |
| `scripts/check`、两个旧 V4 gate | 3 | DROP | 旧仓继续自用；新仓按 G-07/G-09 建立新检查入口 |
| **合计** | **92** | **KEEP 0 / REWRITE 43 / DROP 49** | 闭合 |

说明：

- “其余通用文档”包含编码规范、依赖复用、文件组织、模块契约、部署、测试等能力主题；保留的是问题清单，不是旧答案。
- `backend/LICENSE.txt` 位于 `backend/**` 的 7 个文件内；其内容属于 ThinkPHP 上游说明，不能当成 Peanut Admin 顶层许可证。
- `docker/.gitkeep` 仍按 infra 路径计算，不应单独被误当作可复用资产。

## 6. 历史 294 个 Runtime 资产的处置

旧提交 `a0828f7` 曾有 294 个 backend/frontend/packages/templates/examples/scripts 文件。旧 V4 清单给出 8 KEEP、67 ADAPT、87 REBUILD、132 REMOVE，但该分类以已经废弃的 V4 为目标，不能继续授权迁移。

本次按新架构重新裁决：

| 历史资产 | 处置 | 原因 |
| --- | --- | --- |
| 身份、Context、权限、Portal/Application/Entry/SystemInstance 实现 | DROP | 核心对象、状态机和授权根已改变 |
| DCS、Finance、SimpleMode、多 Actor seed/example | DROP | 具体业务和旧模型污染底座 |
| migration、route、API、Admin 页面 | DROP | 字段、错误、audience、Tenant 和数据权限契约已改变 |
| PHP/JS package 源码 | DROP | public API、包边界和许可证声明不完整，不做兼容 |
| Generator/template 代码 | REWRITE | dry-run、冲突检测、托管文件等问题有价值，但输入 schema 和生成内容全部重写 |
| Admin Shell 布局与错误页 | REWRITE | 只参考交互意图，按 G-06 和当前设计系统重新实现 |
| ThinkPHP/Vue/Vite 宿主接线 | REWRITE | 从官方上游和锁定依赖重新生成，不从历史复制 |
| check/test 脚本 | REWRITE | 测试意图映射到 G-07 编号，旧断言和旧环境变量不保留 |
| compose/docker/env/gitignore | REWRITE | 按新仓、最小服务和安全默认值重建 |

因此，历史代码也没有直接 KEEP。以后若某一段纯算法被认为值得复用，必须先证明：

1. 不依赖旧核心名词、表、Context 或包；
2. 作者和许可证归属清楚；
3. 通过当前测试；
4. 由独立 DDR 批准。

在这些条件全部满足前，默认仍为 REWRITE，而不是复制。

## 7. 密钥、私有信息和业务污染扫描

### 7.1 Gitleaks

使用校验过 SHA-256 的 Gitleaks v8.30.1 对完整 Git 历史执行扫描：

```text
commits scanned: 128
bytes scanned: about 1.89 MB
findings: 0
exit code: 0
```

这证明默认规则没有发现已知格式密钥，不证明旧历史可以公开。

### 7.2 域名、邮箱、手机号和客户信息

历史差异扫描结果：

- 域名来自公开框架/注册表/标准站点、依赖作者 metadata 和明显示例域名。
- 未发现可以确认属于真实客户或内部生产环境的私有域名。
- 两个手机号值位于历史测试脚本，属于测试 fixture；它们不迁入新仓。
- 邮箱来自依赖 metadata 或测试示例，未发现真实客户名单。
- 历史路径明确包含 DCS、Finance 和具体场景 seed/example；这些即使没有客户隐私，也属于不应公开迁移的业务污染。

最终结论仍是禁止公开旧历史。密钥扫描通过只排除一种风险，不能解决许可证、品牌、业务内容和错误架构事实。

## 8. 许可证审计

| 项目 | 发现 | 处置 |
| --- | --- | --- |
| 旧仓顶层 `LICENSE` | 不存在 | 不能公开宣称整个旧仓为 Apache-2.0 或 MIT |
| `backend/LICENSE.txt` | ThinkPHP 上游 Apache-2.0/版权说明 | 只属于上游宿主；新仓若包含对应文件则进入 NOTICE/third-party 记录 |
| 历史 backend `composer.json` | 声明 Apache-2.0 | 不能反向覆盖 frontend、packages 和仓库其他源文件 |
| 历史 frontend `package.json` | 无 license 字段 | 不直接迁移 |
| 历史五个 JS package | 无 license 字段 | 不直接迁移、不发布 |
| 历史 PHP packages | 缺少独立 `composer.json` | 不具备可独立发布的许可证和包身份 |
| LikeAdmin/MineAdmin 等参考 | 只允许参考官方能力和结构 | 禁止复制源码、模板或视觉资产，避免许可证传染和归属不明 |

对新公开仓的推荐：

- Peanut Admin 自研代码采用 `Apache-2.0`。
- 根目录提供标准 `LICENSE`。
- 提供 `NOTICE` 和自动生成的 third-party license inventory。
- Composer/npm package 全部显式声明 `Apache-2.0`。
- 生成器必须保留上游文件的版权和许可证，不把上游版权改写成 Peanut Admin。
- 任何 GPL/AGPL 或来源不明代码进入前必须单独审核，默认拒绝复制。

选择 Apache-2.0 的原因是专利授权和企业使用边界比 MIT 更明确，也与 ThinkPHP 宿主许可证兼容。该推荐将在第二次编码放行时一并由用户确认；未确认前不得创建公开仓许可证或发布 package。

## 9. 新仓准入条件

只有同时满足以下条件，才允许初始化 `/Users/xing/Documents/company-os/repositories/peanut-admin/`：

1. G-09 完成并通过九角色综合复审。
2. 用户在 47 号校准和重新复审后明确使用新的编码批准语，并确认 Apache-2.0 推荐或给出替代许可证。
3. 使用全新空目录和 `git init`，不能 clone/copy 旧 `.git`。
4. 首个提交只包含 G-09 P0-A 白名单。
5. GitHub remote 固定为 `peanut-opensource/peanut-admin`，创建前再次确认组织和仓库不存在冲突。
6. 首次 push 前运行工作树和完整新历史 Gitleaks、许可证清单、依赖审计和 `./scripts/check`。
7. 任何旧资产只能按本文 REWRITE；不得用 `cp -R`、`rsync` 或 cherry-pick 从旧仓迁入。

## 10. G-08 结论

旧仓已经从“错误的下一开发位置”变成“有明确停止线的历史证据库”。它保留了我们为什么做出当前决策的过程，但不再决定 Peanut Admin 如何实现。

新仓的干净，不只是目录里没有旧文件，还必须同时满足：干净 Git 历史、明确项目许可证、官方上游来源、无具体业务、无旧模型兼容目标，以及 G-07 安全门禁从第一批代码开始生效。
