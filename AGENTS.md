# Peanut Admin — Agent Context

> **Read this before touching any file.** This file is the authoritative project state record.
> Last updated: 2026-08-11

---

## 1. 项目身份

| 项目 | 值 |
|------|----|
| 产品名称 | **Peanut Admin**（不使用任何版本后缀） |
| 本地目录 | `/Users/xing/Documents/company-projects/peanut-admin/` |
| GitHub 仓库 | `git@github.com:peanut-business/peanut-admin.git` |
| PC package name | `peanut-admin-pc` |
| 集成分支 | `dev` |
| 稳定分支 | `main` |

此前的独立编排工作区已经删除，内容已归档。当前目录是现行产品代码仓，不是旧编排工作区；后续任务只以本文件记录的目录、仓库和主分支为当前事实源。

---

## 2. 当前状态（2026-08-10）

### 2.1 LikeAdmin 1.9.4 标准版 Parity — ✅ 完成并独立验证

- 9 个 parity commits 已合并并推送到 `main`；已完成使命的功能分支不再作为后续工作基线
- 44 controllers、72 actions（≥ LikeAdmin 标准版 45/68）
- 数据库：`server/database/install.php` + `server/database/migrate.php` + `init.sql` + 24 migrations

**独立验证结果（非 Codex 自报）：**

| 验证项 | 方法 | 结果 |
|--------|------|------|
| Fresh-clone install | `git clone` → 空 MySQL → `php install.php` | 42 tables / 170 menus / 59 configs / 1 admin ✓ |
| 前端路由回归 | Playwright 1.62 headless Chromium 真实浏览器 | 30/30 routes pass ✓ |
| 升级演练 | fresh + legacy adopt + idempotent rerun | 43 tables / 24 migration ledger ✓ |

证据文件：
- `output/playwright/v01/clone-install-summary.json` — Codex 原始报告（参考）
- `output/playwright/v02/summary.json` — **本会话独立验证，可信**
- `output/playwright/v02/*.png` — 9 组截图 + 登录截图

### 2.2 SaaS / 多租户设计 — 🗺️ Roadmap Only，未实现

设计文档位于 `docs/design/saas-roadmap/`（50 个文件）。**后端代码中没有任何多租户实现。** 不要在现有代码里查找 Tenant / pa_tenant 相关逻辑。

### 2.3 产品化正式基线 — 🚧 进行中

- 执行计划：`docs/productization-baseline-plan.md`；能力图：`docs/architecture/core-application-capability-graph.md`
- 已完成：生产 Compose、迁移账本、三端 Docker、产品最低 CI、核心包公开发布、核心仓文档 CI、管理端 Element Plus、标准覆盖 Host、PC/UniApp 无 UI client 消费
- 生产发布：`dev` 已部署到 `peanut-admin.007345.xyz`；登录、文章页、PC、H5 与文档真实 Chromium smoke 通过，证据见 `output/playwright/production-baseline/final-summary.json`
- 当前生产服务器使用 `bundled-db` MySQL profile；局域网 `192.168.192.2` 是开发/历史验收数据库，公网服务器不可直接路由该地址
- 已完成 PB03：`docs/architecture/pb03-ownership-and-migration-gates.md` 已冻结核心通用基础设施、应用产品 Module、唯一实现、Host/override、测试 owner 与逐领域停止线
- PB04 已完成：网站设置、权限 Host、管理员/RBAC CRUD、字典、文件素材、任务/导入导出与日志/维护均形成应用唯一实现、核心候选停止线及测试 owner
- PB05 已完成：会员/标签、权威余额、兼容镜像、分类流水、充值入账与退款形成应用唯一 Runtime；核心 Tenant membership 与 R01/R02 候选未经采用授权
- PB06 已完成：文章/分类/收藏/计数与移动/PC/Tabbar 装修形成应用唯一 Runtime；产品资源保留 Provider provenance，四端共用唯一装修读取 DTO
- PB07 已完成：通知、支付、OAuth 与外部渠道均固定应用唯一 Host；旧 Channel CRUD、重复凭据和未实现的公众号 AES 配置入口已退出，PC/公众号通过固定 API bridge 回跳；核心相邻候选未获下游采用授权
- PB08A 实现已完成：品牌单一 Runtime、中性安装默认、四端消费、包元数据、官网与文档门户均已落地；静态官网门禁通过，唯一桌面/移动 Chromium 验收归 PB08B
- PB08B 正式候选合同已冻结；RC001 无缓存 registry/生产构建通过但数据库编排证据不足，当前按 `PB08B-RC-002` 继承同一候选并执行逐断言数据库、Docker 和唯一桌面/移动 Chromium 验收
- PB08B 通过且许可证/provenance 门禁解决后才可进入 PB09
- Element Plus 证据：`output/playwright/element-plus-baseline/summary.json`，真实 Chromium 登录及 7 个代表业务域全部通过
- 产品化阶段通过后才将 `dev` 合入 `main`；SaaS 作为后续独立目标

---

## 3. 目录结构

```
peanut-admin/
├── server/          # ThinkPHP 8 后端
│   ├── app/adminapi/    # 管理端 API（44 controllers）
│   ├── app/api/         # 前端/小程序 API
│   ├── database/
│   │   ├── install.php  # 一键安装（空库 → 全量初始化）
│   │   ├── init.sql     # 基础表 + 种子数据
│   │   └── migrations/  # 24 个增量迁移
│   └── .env             # DB/JWT 配置（不提交）
├── web/             # 管理端前端（Vue3 + Element Plus）
├── pc/              # PC 消费端（Nuxt3）
├── uniapp/          # 小程序/H5 客户端
├── docs/
│   ├── design/saas-roadmap/   # SaaS 路线图（roadmap only）
│   └── peanut-admin-*.md      # 开发指南、用户手册
└── output/playwright/         # 验证证据
    ├── v01/  # Codex 自报
    ├── v02/  # 独立验证（可信）
    └── element-plus-baseline/  # Element Plus 迁移真实浏览器证据
```

---

## 4. 默认凭据（开发环境）

- 管理员账号：`admin` / `admin123456`
- 管理端 API：`http://127.0.0.1:8000/`（`php think run --port=8000`）
- 前端 Dev：`http://localhost:5173`（`pnpm dev`，位于 `web/`）
- API 前缀：`admin/*`（管理端），`api/*`（前端/小程序）

---

## 5. 命名规范（重要）

LikeAdmin 到 Peanut Admin 的刻意改名，**不是缺失**：

| LikeAdmin 原名 | Peanut Admin 名称 |
|----------------|-------------------|
| `user` | `member` |
| `dev_crontab` | `crontab` |
| `generate_*` | `generator_*` |
| `notice_record` | `notice_log` |
| `notice_setting` | `notice_scene` + `notice_template` |
| `user_auth` | `oauth_*` |

---

## 6. 给 Codex 的工作约定

1. 所有任务在 `/Users/xing/Documents/company-projects/peanut-admin/` 下执行
2. 功能分支命名：`feat/<描述>`，完成后 PR → `dev`；阶段验收通过后 `dev` → `main`
3. 不要创建带产品版本后缀的名称、路径或文件名
4. SaaS 相关实现需求 → 先查阅 `docs/design/saas-roadmap/` 再动手
5. DB 变更：新建 `server/database/migrations/YYYYMMDD-<描述>.sql`，不要直接修改 `init.sql`
