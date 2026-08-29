# Claude 审核结果 — 核心包 / 发布保护 / 生产候选架构

> 审核者：Claude Opus 4.8（独立架构 / 安全 / 开源治理 / 发布工程审核）
> 日期：2026-08-05
> 对应提示词：`docs/2026-08-05-core-packages-production-claude-review-prompt.md`
> 事实源：AGENTS.md、README.md、pc/package.json、server/（实读代码）、
>   docs/design/saas-roadmap/{28,31,32,40,44,47,48,51}、lifecycle v3.3、
>   output/playwright/{s01,ch03}、`git log --all`

```
VERDICT: APPROVED_WITH_BLOCKERS
```

方向（抽核心包、单一 UI、无外部凭据生产加固）成立，且支撑设计（28/31/32/44/47/48）严谨。
阻塞项都出在**执行边界的框定**，不在长期方向。若按提示词字面在**本仓**执行，会摧毁已验证成果。

## 1. Blocking findings

### [P0-1] 提示词把两个不同的仓库合并成一个来审 — 最严重
- **证据**：提示词 §3.1 描述本仓（`server/`+`web/`+`pc/`+`uniapp/`，LikeAdmin parity 应用）；§3.2 描述另一套架构（`backend/`+`frontend/`+`frontend-arco/`+`packages/php`+`packages/web`+`starter/`）。后者**在本仓及其全部分支的 git 历史中从未存在**（`git log --all -- frontend-arco packages backend frontend starter` = 空）。saas-roadmap/README.md 明确：该包架构的内核契约来自**独立仓** `peanut-opensource/peanut-admin` @ `feature/new-backend-tp8`（commit `90da97b`）。决策 D-01/D-02/D-11 一致要求“干净仓库重新开始”，公开脚手架+核心包放 `peanut-opensource`，**不是**本 `peanut-business/peanut-admin` 产品仓。
- **风险**：若按提示词字面在本仓执行 §10（“删除 frontend-arco、统一 Element Plus”），会删掉本仓唯一的、已 30/30 独立验证的 Arco 管理端——本仓根本没有 Element Plus。
- **最低修正**：在任何编码前，先产出一页《仓库映射》明确：(a) 本仓 = LikeAdmin parity 产品底座（Arco），维持现状；(b) 包/内核/Element-Plus 统一 = 独立 rebuild 仓（`peanut-opensource/peanut-admin`）的事，走 D-12 clean-slate 路径；(c) 每条 §5–§11 动作显式标注作用于哪个仓。用户确认此映射后才解冻编码。

### [P0-2] “抽包”与“新建”被混为一谈
- **证据**：§5.1 定义 `kernel` 拥有 `Tenant/TenantMember/平台租户授权`。但本仓 README 自述“当前 `server/` 里没有 `pa_tenant` 表、没有平台/租户分层”，composer psr-4 仅 `app\\`（无 `PeanutAdmin\\`）。故 kernel **无法从本仓抽取**，只能按 rebuild 设计（37/40/47/48）**从零构建**。
- **风险**：低上下文执行者会尝试 `cp`/重构本仓代码去“抽” kernel，直接违反 doc 44「KEEP=0、禁止 `cp -R`/cherry-pick」。
- **最低修正**：明确 kernel/data-permission = 新建（rebuild 仓 P0-A），不是本仓重构。

### [P0-3] 开源许可证血统未清算，README 措辞危险
- **证据**：README 写“**完整复刻 likeadmin 标准版能力**”。doc 44 §8 明确「LikeAdmin/MineAdmin 只允许参考官方能力和结构，**禁止复制源码、模板或视觉资产**」。本仓无根 `LICENSE`；`server/LICENSE.txt` 只是 ThinkPHP 上游 Apache-2.0。实读 16 个 PHP 文件里的 “likeadmin” 均为**行为对齐注释**（“对齐 likeadmin 1.9.4”），倾向 clean-room 重写而非复制——但“完整复刻”字样 + 无 provenance 审计，构成公开发布前的法律风险。
- **风险**：把疑似 LikeAdmin 衍生代码开源，触发许可证传染/归属争议。
- **最低修正**：公开前对本仓做一次 provenance/clean-room 审计（逐文件确认非复制），补根 `LICENSE`(Apache-2.0)+`NOTICE`+第三方清单，README 改“对标/参考 LikeAdmin 标准版能力（clean-room 实现）”。**这是 blocking，但只 block“公开发布”，不 block 内部工作。**

### [P1-4] 权威设计文档内部路径不一致
- **证据**：新仓物理路径三处不一致——doc 44 §9 = `/company-os/repositories/peanut-admin/`；doc 48 §6 = `/Dev/Project/peanut-admin/`；README = GitHub `peanut-opensource/peanut-admin`。
- **风险**：clean-slate `git init` 建在错误路径，或建两个半成品仓。
- **最低修正**：初始化前统一为单一 canonical 路径 + remote，写回 32/44/48。

### [P1-5] payment 包边界与本仓真实耦合矛盾
- **证据**：§5.2 称 payment 包不拥有订单/余额，“订单归属由 Host/业务 Module 决定”。但本仓 `RechargeApplicationService.php:101-105` 直接改 `member->user_money/balance/total_recharge_amount` 并 `save()`，finance 与 member model 紧耦合。
- **风险**：若有人从本仓“抽” payment，会把 member-balance 耦合一起带走，破坏包边界。
- **最低修正**：重申 payment = rebuild 仓按 kernel 契约新建；本仓 finance 不作为抽包来源。

## 2. Package boundary verdict

作用对象：**rebuild 仓**（`peanut-opensource/peanut-admin`），非本 parity 仓。

- **保留（必要核心）**：`peanut-admin/kernel`、`peanut-admin/data-permission`、`peanut-admin/testing`；web 侧 `@peanut-admin/admin-core`(UI 无关)、`@peanut-admin/admin-shell`(Element Plus)、`@peanut-admin/testing`。边界合理。
- **合并进 kernel（不要过早拆包）**：`auth`、`tenancy`、`membership` 保持为 kernel 内部边界。它们与可信上下文、Session、Guard 强耦合，单独拆包只会制造循环依赖——doc 48 的 R2-06/R2-08 已印证同类循环风险。
- **延后（尚无第二消费者，违反 §7 抽包规则）**：`payment`、`oauth-provider`、`channel-wechat`、`import-export`、`ops-console`、`integration-security`、`notification`。先作为 kernel/Host 内部 Module 落地，冻结 public API + Host 消费测试；出现真实第二消费者、且 API 连续两个发布周期稳定后再提升为独立包（与 D-02 一致）。
- **保留为包但收敛**：`settings`、`file-media`、`reference-codes`、`task-job` 属明确跨项目基础设施，可先建内部包，但必须同时冻结 public API 和消费测试。
- **过度设计需砍**：§5.2/§6.2「每个后端功能包都配同名 web 包」在 v0.x 是过度拆包。web 贡献按真实复用需要单独判定，缺 web 包时后端必须仍可工作（§7.6 已隐含，但 §6.2 的枚举清单与之冲突）。

## 3. Naming and release verdict

- **通过**：Composer `peanut-admin/*`、PHP `PeanutAdmin\*`、npm `@peanut-admin/*`。拒绝 `@peanut/*`（与 §8.1、D-14 一致）。
- **通过**：v0.x 全部包共享版本号、制品分别发布/安装；出现 ≥2 真实消费者且 API 稳定两个周期后再评估分仓（D-02）。
- **阻塞发布（非阻塞设计）**：D-14 名称/商标核验（Packagist vendor、npm scope、“花生”商标）必须在**创建公共包前**完成。当前只能视为候选名，不写进不可逆 public API。

## 4. Source exposure verdict

- **开源核心包**：PSR-4 发布源码，**不用** ionCube/SourceGuardian，不靠混淆冒充安全——正确（§9.1、doc 44 §8 一致）。web 包可只发 `dist/*.{js,css,d.ts}`+manifest+LICENSE+NOTICE，不发 src/测试/source map；**但 public export 必须稳定**。
- **私有/商业包**：dist-only + 私有 Registry + 最小权限 Token 可接受；ionCube 仅限**离线交付的 PHP 商业模块**单独评估，且必须附 Loader/PHP 版本矩阵/应急解码流程。
- **红线**：dist-only 是**私有包策略**，不能套到开源核心包上；仓库公私不得改变 public API/测试/升级契约（§9.2 末句，通过）。

## 5. Frontend verdict

- **Element Plus 统一成立** —— **仅对 rebuild 仓**。证据充分：doc 47 §307-310 固定 `frontend/` = Vue3+Vite+Element Plus；提示词 §3.2 证据表显示 `frontend/`(Element Plus，8 轮提交，13 测试，已消费 admin-core) 明显强于 `frontend-arco/`(1 次导入，0 测试，未消费核心包)。删 frontend-arco 的 5 条停止线（行为映射→逐模块验收→全量通过→禁双写→不泄漏 UI 类型进 admin-core）合理，予以通过。
- **阻塞**：该结论**不可套用到本 parity 仓**。本仓管理端 = Arco（`@arco-design/web-vue`，60 页，45 页用 Arco 组件，Vue 3.2），**零 Element Plus**，且是 30/30 已验证的产品。本仓的 Arco admin 维持现状；是否将来被 rebuild 产物取代，是独立决策，不在本轮。

## 6. Production-candidate verdict

- §11 无外部凭据加固清单**基本充分**（CI 门禁、默认拒绝、SSRF/重放/幂等、备份恢复演练、SBOM/NOTICE/release manifest），方向正确。
- **缺口（需补齐才可标 production candidate）**：
  1. **迁移可重跑/升级路径测试**：现有 `install.php` 只验空库全新安装（v02 已独立复现 42 表/170 菜单/59 配置），**未验证非空库升级、迁移幂等、迁移失败停止与前滚**。lifecycle v3.3 的“克隆→跟随升级”模型正依赖此项。
  2. **回滚/迁移 down 或明确前滚策略**：§11.3 只提“回滚说明”，需落为可执行验收。
  3. **密钥治理**：`server/.env` 含明文疑似真实 DB 口令。须确认 `.env` 已被 gitignore、历史无泄漏、并纳入密钥治理清单（见 §7 安全项，但当前清单未点名 .env 明文口令）。
  4. **许可证 clean-room 审计**（同 P0-3）必须进 SBOM/NOTICE。
- 补齐后只能标 `production candidate / external integration pending`；§12 外部验收（真实商户/微信/短信/云存储/域名证书）未完成前，不得宣称对应外部能力生产可用。Playwright s01/ch03 证据诚实（`real_merchant_called:false`、`real_wechat_called:false`），与此一致。

## 7. Revised execution sequence（按依赖，每阶段唯一最低充分验收）

- **Phase 0 — 仓库映射（阻塞全部编码）**
  停止线：用户确认《仓库映射》后才继续。
  验收：一页文档写清「本 parity 仓（Arco，维持）」vs「rebuild 仓（clean-slate，Element Plus，packages）」，并统一 canonical 路径+remote（消解 P1-4）。

- **Phase 1 — 本仓开源合规（仅当决定公开本仓时）**
  停止线：无未归属的复制源码。
  验收：provenance/clean-room 报告 + 根 LICENSE/NOTICE/第三方清单 + 全历史 Gitleaks 通过 + README 措辞更正（消解 P0-3）。

- **Phase 2 — rebuild 仓字段级设计定稿 + 编码放行**
  停止线：doc 48 的批准语「批准按 48 号复审结论开始 P0-A 运行时代码；顶层许可证 Apache-2.0」由用户明确给出前，不 `git init`、不建 GitHub repo、不写 runtime。
  验收：G-01..G-09 全部 Accepted（当前 48 号 = 字段级 PASS，`runtime evidence: NONE YET`）。

- **Phase 3 — rebuild 仓 P0-A 内核（新建，非抽取）**
  停止线：范围严格限 D-12（安装/迁移、邮箱登录、Tenant/TenantMember、平台操作员、租户切换、RBAC、最小部门+对象数据权限、强制隔离、Module/TenantModule、审计、最小 Admin Shell、安装升级测试）。禁 Finance/DCS/商品/库存。
  验收：空库安装 + 全迁移 + schema 不变量 + 跨租户默认拒绝测试全绿。

- **Phase 4 — rebuild 仓核心 web 包**
  停止线：admin-core 保持 UI 无关，admin-shell 绑 Element Plus。
  验收：path/workspace consumer 能装、能跑、能被空白下游消费。

- **Phase 5 — 功能包按需提升 + 生产加固**
  停止线：仅当出现真实第二消费者才把 payment/oauth/channel 等提升为独立包。
  验收：§11 加固清单 + Phase-1 许可证审计 + 迁移升级/幂等测试全过 → 标 `production candidate / external integration pending`。

> 本 parity 仓与 rebuild 仓并行：parity 仓已是可交付底座（Arco，30/30 验证），除非显式决定用 rebuild 产物取代，否则维持现状、独立演进。

## 8. Explicit non-goals（本轮禁止）

沿用提示词 §15 停止线，并强调：
- 不改本仓/任何仓的 PHP、Vue、TS、SQL、测试代码；不装/升/删依赖；不启服务/迁移库/连生产。
- 不改 Git remote、仓库可见性、默认分支、远程历史；不发布 Composer/npm 包。
- 不删 `frontend-arco/` 或任何业务页面（且**本 parity 仓无 frontend-arco**——不得据此误删本仓 Arco admin）。
- 不把外部凭据缺失解释为已生产验收。
- **不创建 rebuild 仓、不执行 P0-A01**，直到 Phase 2 批准语出现。
- **不把包抽取/UI 统一动作作用于本 parity 仓。**

---

### 附：本轮独立核实到的事实（非文档转述）
1. `git log --all -- frontend-arco packages backend frontend starter` = 空 → §3.2 架构从未在本仓存在。
2. `server/composer.json` = `"type":"project"`、psr-4 仅 `app\\` → 本仓是应用非包，无 `PeanutAdmin\\`。
3. `web/package.json` = `arco-design-pro-vue` / `private:true` / `@arco-design/web-vue` / Vue3.2；60 vue 页、45 页用 Arco → 本仓零 Element Plus。
4. `RechargeApplicationService.php:101-108` 内联改写 member 余额三字段 → finance↔member 紧耦合。
5. `server/LICENSE.txt` = ThinkPHP 上游 Apache-2.0；无根 LICENSE。16 处 “likeadmin” 均为对齐注释。
6. Playwright s01/ch03：`real_merchant_called:false`、`real_wechat_called:false` → 外部集成确为注入传输，与 §3.3 一致。
7. 新仓路径三处不一致（company-os / Dev/Project / GitHub peanut-opensource）。

